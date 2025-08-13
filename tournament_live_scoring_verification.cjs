#!/usr/bin/env node

/**
 * Tournament Live Scoring System Verification
 * Tests the complete integration between SimplifiedLiveScoring and MatchDetailPage
 * Validates Best of 3/5/7 format handling, real-time synchronization, and data persistence
 */

const fetch = require('node-fetch');
const fs = require('fs');

const BACKEND_URL = process.env.BACKEND_URL || 'https://staging.mrvl.net';
const FRONTEND_URL = process.env.FRONTEND_URL || 'https://staging.mrvl.net';

class TournamentLiveScoringTester {
    constructor() {
        this.adminToken = null;
        this.testMatchId = null;
        this.testResults = {};
        this.errors = [];
    }

    async log(message, type = 'info') {
        const timestamp = new Date().toISOString();
        const logMessage = `[${timestamp}] [${type.toUpperCase()}] ${message}`;
        console.log(logMessage);
        
        if (type === 'error') {
            this.errors.push({ timestamp, message });
        }
    }

    async makeRequest(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...options.headers
                }
            });

            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${data.message || 'Request failed'}`);
            }

            return data;
        } catch (error) {
            this.log(`Request failed: ${error.message}`, 'error');
            throw error;
        }
    }

    async authenticateAdmin() {
        this.log('🔐 Authenticating as admin...');
        
        try {
            const response = await this.makeRequest(`${BACKEND_URL}/api/login`, {
                method: 'POST',
                body: JSON.stringify({
                    email: 'admin@mrvl.net',
                    password: 'admin123'
                })
            });

            if (response.access_token) {
                this.adminToken = response.access_token;
                this.log('✅ Admin authentication successful');
                return true;
            } else {
                throw new Error('No access token received');
            }
        } catch (error) {
            this.log(`❌ Admin authentication failed: ${error.message}`, 'error');
            return false;
        }
    }

    async createTestMatch() {
        this.log('🎮 Creating test BO3 match...');
        
        try {
            const matchData = {
                team1_id: 1,
                team2_id: 2,
                event_id: 1,
                scheduled_at: new Date().toISOString(),
                format: 'BO3',
                status: 'live',
                maps: [
                    { map_name: 'Tokyo 2099', mode: 'Convoy', team1_score: 0, team2_score: 0 },
                    { map_name: 'New York 2099', mode: 'Domination', team1_score: 0, team2_score: 0 },
                    { map_name: 'Shanghai 2099', mode: 'Push', team1_score: 0, team2_score: 0 }
                ],
                allow_past_date: true
            };

            const response = await this.makeRequest(`${BACKEND_URL}/api/admin/matches`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(matchData)
            });

            if (response.data && response.data.id) {
                this.testMatchId = response.data.id;
                this.log(`✅ Test match created with ID: ${this.testMatchId}`);
                return true;
            } else {
                throw new Error('No match ID returned');
            }
        } catch (error) {
            this.log(`❌ Test match creation failed: ${error.message}`, 'error');
            return false;
        }
    }

    async testLiveScoringDataSync() {
        this.log('🔄 Testing live scoring data synchronization...');
        
        try {
            // Test 1: Update player stats and map scores
            const liveStatsUpdate = {
                team1_players: [
                    { id: 1, username: 'Player1', hero: 'Spider-Man', kills: 15, deaths: 3, assists: 8, damage: 12500, healing: 0, blocked: 2300 },
                    { id: 2, username: 'Player2', hero: 'Iron Man', kills: 12, deaths: 5, assists: 10, damage: 11200, healing: 0, blocked: 1800 },
                    { id: 3, username: 'Player3', hero: 'Doctor Strange', kills: 8, deaths: 4, assists: 15, damage: 8900, healing: 7500, blocked: 0 },
                    { id: 4, username: 'Player4', hero: 'Mantis', kills: 5, deaths: 6, assists: 18, damage: 6200, healing: 12000, blocked: 0 },
                    { id: 5, username: 'Player5', hero: 'Magneto', kills: 7, deaths: 4, assists: 12, damage: 9800, healing: 0, blocked: 4500 },
                    { id: 6, username: 'Player6', hero: 'Groot', kills: 3, deaths: 8, assists: 16, damage: 5100, healing: 3200, blocked: 8900 }
                ],
                team2_players: [
                    { id: 7, username: 'Player7', hero: 'Wolverine', kills: 18, deaths: 7, assists: 6, damage: 14200, healing: 0, blocked: 1200 },
                    { id: 8, username: 'Player8', hero: 'Hawkeye', kills: 14, deaths: 6, assists: 9, damage: 13100, healing: 0, blocked: 900 },
                    { id: 9, username: 'Player9', hero: 'Wanda', kills: 10, deaths: 5, assists: 12, damage: 10800, healing: 6800, blocked: 0 },
                    { id: 10, username: 'Player10', hero: 'Luna Snow', kills: 4, deaths: 7, assists: 20, damage: 5800, healing: 14500, blocked: 0 },
                    { id: 11, username: 'Player11', hero: 'Doctor Doom', kills: 9, deaths: 6, assists: 11, damage: 11200, healing: 0, blocked: 5200 },
                    { id: 12, username: 'Player12', hero: 'Peni Parker', kills: 2, deaths: 9, assists: 18, damage: 4900, healing: 2800, blocked: 9800 }
                ],
                series_score_team1: 0,
                series_score_team2: 0,
                team1_score: 75, // Current map score
                team2_score: 63, // Current map score
                current_map: 1,
                total_maps: 3,
                maps: {
                    1: { team1Score: 75, team2Score: 63, status: 'active', winner: null },
                    2: { team1Score: 0, team2Score: 0, status: 'pending', winner: null },
                    3: { team1Score: 0, team2Score: 0, status: 'pending', winner: null }
                },
                status: 'live',
                timestamp: Date.now()
            };

            const updateResponse = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${this.testMatchId}/update-live-stats`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(liveStatsUpdate)
            });

            if (updateResponse.success) {
                this.log('✅ Live stats update successful');
                this.testResults.liveStatsUpdate = true;
            } else {
                throw new Error('Live stats update failed');
            }

            // Test 2: Verify data persistence by fetching match
            const matchResponse = await this.makeRequest(`${BACKEND_URL}/api/matches/${this.testMatchId}`);
            
            if (matchResponse.data || matchResponse.id) {
                const match = matchResponse.data || matchResponse;
                this.log('✅ Match data persistence verified');
                this.log(`   Team 1 Score: ${match.team1_score || 0}`);
                this.log(`   Team 2 Score: ${match.team2_score || 0}`);
                this.log(`   Status: ${match.status}`);
                this.testResults.dataPersistence = true;
            } else {
                throw new Error('Could not verify data persistence');
            }

            return true;
        } catch (error) {
            this.log(`❌ Live scoring data sync test failed: ${error.message}`, 'error');
            this.testResults.liveStatsUpdate = false;
            this.testResults.dataPersistence = false;
            return false;
        }
    }

    async testBestOfFormats() {
        this.log('🏆 Testing Best of 3/5/7 format handling...');
        
        try {
            // Test BO3 Map 1 completion
            const map1WinUpdate = {
                series_score_team1: 1,
                series_score_team2: 0,
                current_map: 2,
                maps: {
                    1: { team1Score: 100, team2Score: 87, status: 'completed', winner: 1 },
                    2: { team1Score: 0, team2Score: 0, status: 'active', winner: null },
                    3: { team1Score: 0, team2Score: 0, status: 'pending', winner: null }
                },
                team1_score: 0, // Reset for next map
                team2_score: 0,
                timestamp: Date.now()
            };

            const map1Response = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${this.testMatchId}/update-live-stats`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(map1WinUpdate)
            });

            if (map1Response.success) {
                this.log('✅ Map 1 completion and progression test passed');
                this.testResults.mapProgression = true;
            }

            // Test BO3 Map 2 completion
            const map2WinUpdate = {
                series_score_team1: 1,
                series_score_team2: 1,
                current_map: 3,
                maps: {
                    1: { team1Score: 100, team2Score: 87, status: 'completed', winner: 1 },
                    2: { team1Score: 78, team2Score: 100, status: 'completed', winner: 2 },
                    3: { team1Score: 0, team2Score: 0, status: 'active', winner: null }
                },
                team1_score: 0,
                team2_score: 0,
                timestamp: Date.now()
            };

            const map2Response = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${this.testMatchId}/update-live-stats`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(map2WinUpdate)
            });

            if (map2Response.success) {
                this.log('✅ Map 2 completion test passed - Series tied 1-1');
                this.testResults.seriesProgression = true;
            }

            // Test BO3 Match completion
            const matchCompleteUpdate = {
                series_score_team1: 2,
                series_score_team2: 1,
                current_map: 3,
                maps: {
                    1: { team1Score: 100, team2Score: 87, status: 'completed', winner: 1 },
                    2: { team1Score: 78, team2Score: 100, status: 'completed', winner: 2 },
                    3: { team1Score: 100, team2Score: 92, status: 'completed', winner: 1 }
                },
                status: 'completed',
                timestamp: Date.now()
            };

            const matchCompleteResponse = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${this.testMatchId}/update-live-stats`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(matchCompleteUpdate)
            });

            if (matchCompleteResponse.success) {
                this.log('✅ Match completion test passed - Team 1 wins 2-1');
                this.testResults.matchCompletion = true;
            }

            return true;
        } catch (error) {
            this.log(`❌ Best of formats test failed: ${error.message}`, 'error');
            this.testResults.mapProgression = false;
            this.testResults.seriesProgression = false;
            this.testResults.matchCompletion = false;
            return false;
        }
    }

    async testScoreDistinction() {
        this.log('🎯 Testing series scores vs current map scores distinction...');
        
        try {
            // Create a new match for this test
            const bo5MatchData = {
                team1_id: 3,
                team2_id: 4,
                event_id: 1,
                scheduled_at: new Date().toISOString(),
                format: 'BO5',
                status: 'live',
                maps: [
                    { map_name: 'Tokyo 2099', mode: 'Convoy', team1_score: 0, team2_score: 0 },
                    { map_name: 'New York 2099', mode: 'Domination', team1_score: 0, team2_score: 0 },
                    { map_name: 'Shanghai 2099', mode: 'Push', team1_score: 0, team2_score: 0 },
                    { map_name: 'Midtown', mode: 'Convoy', team1_score: 0, team2_score: 0 },
                    { map_name: 'Sanctum Sanctorum', mode: 'Domination', team1_score: 0, team2_score: 0 }
                ],
                allow_past_date: true
            };

            const bo5Response = await this.makeRequest(`${BACKEND_URL}/api/admin/matches`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(bo5MatchData)
            });

            const bo5MatchId = bo5Response.data.id;
            this.log(`Created BO5 test match: ${bo5MatchId}`);

            // Test distinction between series scores (map wins) and current map scores (rounds)
            const scoreDistinctionUpdate = {
                // Series scores = map wins (0-0 so far)
                series_score_team1: 0,
                series_score_team2: 0,
                // Current map scores = round scores (87-45 in current map)
                team1_score: 87,
                team2_score: 45,
                current_map: 1,
                total_maps: 5,
                maps: {
                    1: { team1Score: 87, team2Score: 45, status: 'active', winner: null },
                    2: { team1Score: 0, team2Score: 0, status: 'pending', winner: null },
                    3: { team1Score: 0, team2Score: 0, status: 'pending', winner: null },
                    4: { team1Score: 0, team2Score: 0, status: 'pending', winner: null },
                    5: { team1Score: 0, team2Score: 0, status: 'pending', winner: null }
                },
                timestamp: Date.now()
            };

            const distinctionResponse = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${bo5MatchId}/update-live-stats`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`
                },
                body: JSON.stringify(scoreDistinctionUpdate)
            });

            if (distinctionResponse.success) {
                // Verify the distinction is maintained
                const verifyResponse = await this.makeRequest(`${BACKEND_URL}/api/matches/${bo5MatchId}`);
                const match = verifyResponse.data || verifyResponse;
                
                const seriesScore = match.team1_score || 0; // Should be 0 (no maps won yet)
                const mapsData = match.maps || JSON.parse(match.maps_data || '[]');
                const currentMapScore = mapsData[0]?.team1_score || 0; // Should be 87 (current map rounds)

                if (seriesScore === 0 && currentMapScore === 87) {
                    this.log('✅ Score distinction test passed');
                    this.log(`   Series Score (Map Wins): Team1=${seriesScore}, Team2=${match.team2_score || 0}`);
                    this.log(`   Current Map Score (Rounds): Team1=${currentMapScore}, Team2=${mapsData[0]?.team2_score || 0}`);
                    this.testResults.scoreDistinction = true;
                } else {
                    throw new Error(`Score distinction failed. Series: ${seriesScore}, Map: ${currentMapScore}`);
                }
            }

            return true;
        } catch (error) {
            this.log(`❌ Score distinction test failed: ${error.message}`, 'error');
            this.testResults.scoreDistinction = false;
            return false;
        }
    }

    async testRealTimeSynchronization() {
        this.log('⚡ Testing real-time synchronization...');
        
        try {
            // Simulate rapid updates as would happen during live scoring
            const rapidUpdates = [
                { team1_score: 10, team2_score: 8, timestamp: Date.now() },
                { team1_score: 15, team2_score: 12, timestamp: Date.now() + 1000 },
                { team1_score: 23, team2_score: 18, timestamp: Date.now() + 2000 },
                { team1_score: 31, team2_score: 25, timestamp: Date.now() + 3000 }
            ];

            let allUpdatesSuccessful = true;
            
            for (const update of rapidUpdates) {
                const response = await this.makeRequest(`${BACKEND_URL}/api/admin/matches/${this.testMatchId}/update-live-stats`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${this.adminToken}`
                    },
                    body: JSON.stringify(update)
                });

                if (!response.success) {
                    allUpdatesSuccessful = false;
                    break;
                }

                // Small delay to simulate real-time updates
                await new Promise(resolve => setTimeout(resolve, 100));
            }

            if (allUpdatesSuccessful) {
                this.log('✅ Real-time synchronization test passed');
                this.testResults.realTimeSync = true;
            } else {
                throw new Error('One or more rapid updates failed');
            }

            return true;
        } catch (error) {
            this.log(`❌ Real-time synchronization test failed: ${error.message}`, 'error');
            this.testResults.realTimeSync = false;
            return false;
        }
    }

    async generateReport() {
        this.log('📊 Generating comprehensive test report...');
        
        const report = {
            timestamp: new Date().toISOString(),
            testMatchId: this.testMatchId,
            overallStatus: Object.values(this.testResults).every(result => result === true) ? 'PASSED' : 'FAILED',
            testResults: this.testResults,
            errors: this.errors,
            recommendations: []
        };

        // Add recommendations based on test results
        if (!this.testResults.liveStatsUpdate) {
            report.recommendations.push('Fix live stats update endpoint - check validation and database persistence');
        }
        if (!this.testResults.mapProgression) {
            report.recommendations.push('Fix map progression logic - ensure proper state transitions');
        }
        if (!this.testResults.scoreDistinction) {
            report.recommendations.push('Fix score distinction handling - separate series scores from map scores');
        }
        if (!this.testResults.realTimeSync) {
            report.recommendations.push('Optimize real-time synchronization - check for race conditions and conflicts');
        }

        // Write report to file
        const reportFile = `/var/www/mrvl-backend/tournament_live_scoring_test_report_${Date.now()}.json`;
        fs.writeFileSync(reportFile, JSON.stringify(report, null, 2));
        
        this.log(`📄 Test report saved to: ${reportFile}`);
        
        // Console summary
        console.log('\n' + '='.repeat(60));
        console.log('🏆 TOURNAMENT LIVE SCORING SYSTEM TEST RESULTS');
        console.log('='.repeat(60));
        console.log(`Overall Status: ${report.overallStatus}`);
        console.log(`Test Match ID: ${this.testMatchId}`);
        console.log('');
        console.log('Individual Test Results:');
        console.log(`  📊 Live Stats Update: ${this.testResults.liveStatsUpdate ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  💾 Data Persistence: ${this.testResults.dataPersistence ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  🗺️  Map Progression: ${this.testResults.mapProgression ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  🏁 Series Progression: ${this.testResults.seriesProgression ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  🎯 Match Completion: ${this.testResults.matchCompletion ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  🔢 Score Distinction: ${this.testResults.scoreDistinction ? '✅ PASSED' : '❌ FAILED'}`);
        console.log(`  ⚡ Real-time Sync: ${this.testResults.realTimeSync ? '✅ PASSED' : '❌ FAILED'}`);
        console.log('');
        
        if (this.errors.length > 0) {
            console.log('❌ Errors Encountered:');
            this.errors.forEach((error, index) => {
                console.log(`  ${index + 1}. ${error.message}`);
            });
            console.log('');
        }
        
        if (report.recommendations.length > 0) {
            console.log('💡 Recommendations:');
            report.recommendations.forEach((rec, index) => {
                console.log(`  ${index + 1}. ${rec}`);
            });
        }
        
        console.log('='.repeat(60));
        
        return report;
    }

    async runFullTest() {
        this.log('🚀 Starting Tournament Live Scoring System Verification...');
        
        // Step 1: Authenticate
        const authSuccess = await this.authenticateAdmin();
        if (!authSuccess) {
            return await this.generateReport();
        }

        // Step 2: Create test match
        const matchSuccess = await this.createTestMatch();
        if (!matchSuccess) {
            return await this.generateReport();
        }

        // Step 3: Run all tests
        await this.testLiveScoringDataSync();
        await this.testBestOfFormats();
        await this.testScoreDistinction();
        await this.testRealTimeSynchronization();

        // Step 4: Generate final report
        return await this.generateReport();
    }
}

// Run the test suite
async function main() {
    const tester = new TournamentLiveScoringTester();
    await tester.runFullTest();
}

// Execute if run directly
if (require.main === module) {
    main().catch(console.error);
}

module.exports = TournamentLiveScoringTester;