/**
 * 🔥 IMMEDIATE LIVE UPDATES SYSTEM - Zero Delay Implementation
 * 
 * This demonstrates how to implement immediate updates between
 * live scoring panel and match detail page with ZERO delay
 */

class ImmediateLiveUpdates {
    constructor(matchId, authToken) {
        this.matchId = matchId;
        this.authToken = authToken;
        this.eventSource = null;
        this.subscribers = new Set();
        this.lastUpdateTimestamp = 0;
        
        // Start SSE connection for immediate updates
        this.connectSSE();
    }

    /**
     * Connect to Server-Sent Events stream for instant updates
     */
    connectSSE() {
        const url = `/api/admin/matches/${this.matchId}/stream`;
        this.eventSource = new EventSource(url);
        
        this.eventSource.onopen = () => {
            console.log('🚀 Connected to immediate updates stream');
        };
        
        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                
                if (data.type === 'connection') {
                    console.log('✅ SSE Connection established:', data.message);
                    return;
                }
                
                // Immediate update received - notify all subscribers
                this.notifySubscribers(data);
                
            } catch (error) {
                console.error('Error parsing SSE data:', error);
            }
        };
        
        this.eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);
            // Auto-reconnect after 3 seconds
            setTimeout(() => this.connectSSE(), 3000);
        };
    }

    /**
     * Subscribe to immediate updates
     * @param {Function} callback - Function to call when updates arrive
     */
    subscribe(callback) {
        this.subscribers.add(callback);
        
        // Return unsubscribe function
        return () => {
            this.subscribers.delete(callback);
        };
    }

    /**
     * Notify all subscribers of an update
     */
    notifySubscribers(updateData) {
        this.lastUpdateTimestamp = updateData.timestamp;
        
        this.subscribers.forEach(callback => {
            try {
                callback(updateData);
            } catch (error) {
                console.error('Error in update subscriber:', error);
            }
        });
    }

    /**
     * 🚀 OPTIMISTIC UPDATE - Update UI immediately, then sync with backend
     * 
     * This makes updates appear INSTANTLY on both live scoring and match detail pages
     */
    async optimisticUpdate(updateType, updateData) {
        // 1. IMMEDIATE UI UPDATE - Apply changes instantly to local state
        const optimisticData = {
            match_id: this.matchId,
            update_type: updateType,
            timestamp: Date.now(),
            iso_timestamp: new Date().toISOString(),
            optimistic: true,
            match_data: updateData
        };
        
        // Immediately notify subscribers (updates UI instantly)
        this.notifySubscribers(optimisticData);
        
        // 2. BACKEND SYNC - Send to server for persistence and broadcast to other clients
        try {
            const response = await fetch(`/api/admin/matches/${this.matchId}/optimistic-update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.authToken}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    update_type: updateType,
                    ...updateData
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            console.log('✅ Optimistic update confirmed:', result);
            
        } catch (error) {
            console.error('❌ Optimistic update failed:', error);
            
            // Revert the optimistic update if backend fails
            const revertData = {
                ...optimisticData,
                reverted: true,
                error: error.message
            };
            this.notifySubscribers(revertData);
        }
    }

    /**
     * Quick methods for common updates
     */
    updateScore(team1Score, team2Score) {
        return this.optimisticUpdate('score_update', {
            team1_score: team1Score,
            team2_score: team2Score
        });
    }

    updatePlayerStats(playerId, stats) {
        return this.optimisticUpdate('player_stats', {
            player_id: playerId,
            stats: stats
        });
    }

    updateMatchStatus(status) {
        return this.optimisticUpdate('match_control', {
            status: status
        });
    }

    /**
     * Cleanup when component unmounts
     */
    destroy() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        this.subscribers.clear();
    }
}

/**
 * 🔥 USAGE EXAMPLE - How to implement in your React components
 */

// In your Live Scoring Panel component:
class LiveScoringPanel {
    constructor(matchId) {
        this.liveUpdates = new ImmediateLiveUpdates(matchId, authToken);
        
        // Subscribe to updates from other sources
        this.unsubscribe = this.liveUpdates.subscribe((updateData) => {
            if (!updateData.optimistic) {
                // Update came from another client - sync our UI
                this.syncUIWithUpdate(updateData);
            }
        });
    }
    
    // When user changes a score
    async onScoreChange(team1Score, team2Score) {
        // This will update BOTH live scoring panel AND match detail page INSTANTLY
        await this.liveUpdates.updateScore(team1Score, team2Score);
    }
    
    // When user updates player stats
    async onPlayerStatsChange(playerId, newStats) {
        // This will update BOTH pages INSTANTLY
        await this.liveUpdates.updatePlayerStats(playerId, newStats);
    }
    
    syncUIWithUpdate(updateData) {
        // Update your component state with the new data
        if (updateData.match_data) {
            this.setState({
                scores: updateData.match_data.series_score,
                playerStats: updateData.match_data.player_stats,
                // ... other data
            });
        }
    }
    
    componentWillUnmount() {
        this.unsubscribe();
        this.liveUpdates.destroy();
    }
}

// In your Match Detail Page component:
class MatchDetailPage {
    constructor(matchId) {
        this.liveUpdates = new ImmediateLiveUpdates(matchId, authToken);
        
        // Subscribe to ALL updates (from live scoring panel)
        this.unsubscribe = this.liveUpdates.subscribe((updateData) => {
            // Immediately update match detail page when live scoring changes
            this.updateMatchDisplay(updateData);
        });
    }
    
    updateMatchDisplay(updateData) {
        // Update your match detail page state immediately
        if (updateData.match_data) {
            this.setState({
                match: {
                    ...this.state.match,
                    ...updateData.match_data
                },
                lastUpdate: updateData.timestamp
            });
        }
    }
    
    componentWillUnmount() {
        this.unsubscribe();
        this.liveUpdates.destroy();
    }
}

/**
 * 🔥 RESULT: 
 * 
 * - Updates in live scoring panel appear INSTANTLY on match detail page
 * - Updates in match detail page appear INSTANTLY on live scoring panel  
 * - Zero delay between pages
 * - Optimistic updates make UI feel immediate
 * - Server-Sent Events provide real-time sync
 * - Automatic fallback if optimistic updates fail
 */

// Export for use in your application
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImmediateLiveUpdates;
}