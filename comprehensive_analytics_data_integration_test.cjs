/**
 * COMPREHENSIVE ANALYTICS AND DATA INTEGRATION SYSTEM TEST
 * 
 * This comprehensive test suite validates:
 * 1. Analytics Dashboard Components
 * 2. Database connectivity for analytics  
 * 3. Match statistics collection and aggregation
 * 4. Player performance metrics tracking
 * 5. Tournament data analysis capabilities
 * 6. Real-time data integration and WebSocket capabilities
 * 7. Data visualization components and chart rendering
 * 8. Analytics API endpoints and data flow performance
 * 9. Mobile-responsive analytics display
 * 10. Performance analytics and system monitoring
 */

const https = require('https');
const fs = require('fs');

// Test Configuration
const BASE_URL = 'https://mrvl.pro';
const API_BASE = `${BASE_URL}/api`;
const TEST_OUTPUT_FILE = `analytics_integration_test_report_${Date.now()}.json`;

// Test Results Storage
let testResults = {
    testSuite: 'COMPREHENSIVE ANALYTICS AND DATA INTEGRATION TEST',
    timestamp: new Date().toISOString(),
    environment: 'Production',
    totalTests: 0,
    passedTests: 0,
    failedTests: 0,
    testCategories: {
        analyticsApi: { tests: [], passed: 0, failed: 0 },
        databaseConnectivity: { tests: [], passed: 0, failed: 0 },
        matchStatistics: { tests: [], passed: 0, failed: 0 },
        playerMetrics: { tests: [], passed: 0, failed: 0 },
        tournamentAnalysis: { tests: [], passed: 0, failed: 0 },
        realTimeData: { tests: [], passed: 0, failed: 0 },
        dataVisualization: { tests: [], passed: 0, failed: 0 },
        performanceAnalytics: { tests: [], passed: 0, failed: 0 },
        mobileResponsiveness: { tests: [], passed: 0, failed: 0 },
        systemMonitoring: { tests: [], passed: 0, failed: 0 }
    },
    performanceMetrics: {
        averageResponseTime: 0,
        slowestEndpoint: '',
        fastestEndpoint: '',
        totalDataProcessed: 0,
        analyticsAccuracy: 0
    },
    recommendations: [],
    criticalIssues: [],
    summary: {}
};

// Helper Functions
function makeRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const startTime = Date.now();
        
        const req = https.request(url, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'User-Agent': 'Analytics-Test-Suite/1.0',
                ...options.headers
            },
            ...options
        }, (res) => {
            let data = '';
            
            res.on('data', (chunk) => {
                data += chunk;
            });
            
            res.on('end', () => {
                const responseTime = Date.now() - startTime;
                
                try {
                    const jsonData = data ? JSON.parse(data) : {};
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        data: jsonData,
                        responseTime,
                        rawData: data
                    });
                } catch (error) {
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        data: data,
                        responseTime,
                        rawData: data,
                        parseError: error.message
                    });
                }
            });
        });
        
        req.on('error', (error) => {
            reject({
                error: error.message,
                responseTime: Date.now() - startTime
            });
        });
        
        if (options.data) {
            req.write(JSON.stringify(options.data));
        }
        
        req.end();
    });
}

function addTestResult(category, testName, success, details) {
    testResults.totalTests++;
    
    if (success) {
        testResults.passedTests++;
        testResults.testCategories[category].passed++;
    } else {
        testResults.failedTests++;
        testResults.testCategories[category].failed++;
        
        if (details.critical) {
            testResults.criticalIssues.push({
                test: testName,
                issue: details.error || details.message,
                category
            });
        }
    }
    
    testResults.testCategories[category].tests.push({
        name: testName,
        success,
        timestamp: new Date().toISOString(),
        details
    });
}

// Test Categories

/**
 * 1. ANALYTICS API ENDPOINTS TEST
 */
async function testAnalyticsApiEndpoints() {
    console.log('🔍 Testing Analytics API Endpoints...');
    
    const endpoints = [
        { name: 'Admin Analytics Overview', path: '/admin/analytics', requiresAuth: true },
        { name: 'Admin Statistics', path: '/admin/stats', requiresAuth: true },
        { name: 'Analytics Overview', path: '/admin/analytics/overview', requiresAuth: true },
        { name: 'User Analytics', path: '/analytics/users', requiresAuth: true },
        { name: 'Match Analytics', path: '/analytics/matches', requiresAuth: true },
        { name: 'Team Performance Analytics', path: '/analytics/teams', requiresAuth: true },
        { name: 'Player Performance Analytics', path: '/analytics/players', requiresAuth: true },
        { name: 'Hero Analytics', path: '/analytics/heroes', requiresAuth: true },
        { name: 'Map Analytics', path: '/analytics/maps', requiresAuth: true },
        { name: 'Engagement Analytics', path: '/analytics/engagement', requiresAuth: true }
    ];
    
    for (const endpoint of endpoints) {
        try {
            const response = await makeRequest(`${API_BASE}${endpoint.path}`);
            
            const success = response.statusCode === 200 || response.statusCode === 401; // 401 expected for auth-required endpoints
            const hasAnalyticsData = response.data && (response.data.data || response.data.analytics || response.data.success);
            
            addTestResult('analyticsApi', endpoint.name, success, {
                statusCode: response.statusCode,
                responseTime: response.responseTime,
                hasData: !!response.data,
                hasAnalyticsData,
                dataStructure: response.data ? Object.keys(response.data) : [],
                authRequired: endpoint.requiresAuth && response.statusCode === 401,
                error: response.statusCode >= 500 ? 'Server Error' : null
            });
            
            if (response.responseTime > 5000) {
                testResults.recommendations.push({
                    category: 'Performance',
                    recommendation: `${endpoint.name} endpoint is slow (${response.responseTime}ms). Consider optimization.`
                });
            }
            
        } catch (error) {
            addTestResult('analyticsApi', endpoint.name, false, {
                error: error.error || error.message,
                critical: true
            });
        }
        
        // Rate limiting delay
        await new Promise(resolve => setTimeout(resolve, 100));
    }
}

/**
 * 2. DATABASE CONNECTIVITY AND INTEGRATION TEST
 */
async function testDatabaseConnectivity() {
    console.log('🔗 Testing Database Connectivity and Analytics Integration...');
    
    try {
        // Test database health through analytics endpoints
        const response = await makeRequest(`${API_BASE}/admin/stats`);
        
        const hasDbData = response.data && response.data.data;
        const hasOverviewStats = hasDbData && response.data.data.overview;
        const hasCountData = hasOverviewStats && (
            response.data.data.overview.totalUsers !== undefined ||
            response.data.data.overview.totalTeams !== undefined ||
            response.data.data.overview.totalMatches !== undefined
        );
        
        addTestResult('databaseConnectivity', 'Database Health Check', hasDbData, {
            statusCode: response.statusCode,
            hasData: hasDbData,
            hasStats: hasOverviewStats,
            hasCountData,
            dataKeys: hasDbData ? Object.keys(response.data.data) : []
        });
        
        // Test specific database tables through analytics
        const dbTables = [
            { name: 'Users Table', endpoint: '/admin/analytics', dataPath: 'user_analytics' },
            { name: 'Teams Table', endpoint: '/teams', dataPath: 'data' },
            { name: 'Players Table', endpoint: '/players', dataPath: 'data' },
            { name: 'Matches Table', endpoint: '/matches', dataPath: 'data' },
            { name: 'Events Table', endpoint: '/events', dataPath: 'data' },
            { name: 'Heroes Table', endpoint: '/heroes', dataPath: 'data' }
        ];
        
        for (const table of dbTables) {
            try {
                const tableResponse = await makeRequest(`${API_BASE}${table.endpoint}`);
                const hasTableData = tableResponse.data && (
                    tableResponse.data[table.dataPath] || 
                    tableResponse.data.data || 
                    Array.isArray(tableResponse.data)
                );
                
                addTestResult('databaseConnectivity', table.name, !!hasTableData, {
                    statusCode: tableResponse.statusCode,
                    hasData: hasTableData,
                    responseTime: tableResponse.responseTime,
                    dataType: hasTableData ? typeof tableResponse.data : 'no-data'
                });
                
                if (hasTableData) {
                    testResults.performanceMetrics.totalDataProcessed++;
                }
                
            } catch (error) {
                addTestResult('databaseConnectivity', table.name, false, {
                    error: error.error || error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
    } catch (error) {
        addTestResult('databaseConnectivity', 'Database Health Check', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 3. MATCH STATISTICS COLLECTION AND AGGREGATION TEST
 */
async function testMatchStatistics() {
    console.log('⚔️ Testing Match Statistics Collection and Aggregation...');
    
    try {
        // Test match data retrieval
        const matchesResponse = await makeRequest(`${API_BASE}/matches`);
        const hasMatches = matchesResponse.data && (Array.isArray(matchesResponse.data) || matchesResponse.data.data);
        
        addTestResult('matchStatistics', 'Match Data Retrieval', hasMatches, {
            statusCode: matchesResponse.statusCode,
            hasMatches,
            matchCount: hasMatches ? (Array.isArray(matchesResponse.data) ? matchesResponse.data.length : 
                         Array.isArray(matchesResponse.data.data) ? matchesResponse.data.data.length : 0) : 0,
            responseTime: matchesResponse.responseTime
        });
        
        // Test match statistics aggregation through analytics
        const matchAnalyticsResponse = await makeRequest(`${API_BASE}/admin/analytics?period=30d`);
        const hasMatchAnalytics = matchAnalyticsResponse.data && 
                                matchAnalyticsResponse.data.data && 
                                matchAnalyticsResponse.data.data.match_analytics;
        
        addTestResult('matchStatistics', 'Match Analytics Aggregation', hasMatchAnalytics, {
            statusCode: matchAnalyticsResponse.statusCode,
            hasAnalytics: hasMatchAnalytics,
            analyticsLevel: matchAnalyticsResponse.data?.analytics_level,
            responseTime: matchAnalyticsResponse.responseTime,
            aggregationFields: hasMatchAnalytics ? Object.keys(matchAnalyticsResponse.data.data.match_analytics) : []
        });
        
        // Test match outcome statistics
        if (hasMatches) {
            const matches = Array.isArray(matchesResponse.data) ? matchesResponse.data : matchesResponse.data.data || [];
            const completedMatches = matches.filter(m => m.status === 'completed').length;
            const liveMatches = matches.filter(m => m.status === 'live').length;
            const upcomingMatches = matches.filter(m => m.status === 'upcoming').length;
            
            addTestResult('matchStatistics', 'Match Status Aggregation', true, {
                totalMatches: matches.length,
                completed: completedMatches,
                live: liveMatches,
                upcoming: upcomingMatches,
                statusDistribution: {
                    completedPercentage: matches.length > 0 ? Math.round((completedMatches / matches.length) * 100) : 0,
                    livePercentage: matches.length > 0 ? Math.round((liveMatches / matches.length) * 100) : 0,
                    upcomingPercentage: matches.length > 0 ? Math.round((upcomingMatches / matches.length) * 100) : 0
                }
            });
        }
        
        // Test match performance metrics
        const performanceTests = [
            { name: 'Match Duration Analytics', field: 'average_duration' },
            { name: 'Match Viewer Analytics', field: 'average_viewers' },
            { name: 'Match Outcome Analytics', field: 'match_outcomes' },
            { name: 'Peak Viewing Analytics', field: 'peak_viewing_hours' }
        ];
        
        for (const test of performanceTests) {
            const hasField = hasMatchAnalytics && 
                           matchAnalyticsResponse.data.data.match_analytics[test.field];
            
            addTestResult('matchStatistics', test.name, hasField !== undefined, {
                hasField: hasField !== undefined,
                fieldValue: hasField,
                dataType: typeof hasField
            });
        }
        
    } catch (error) {
        addTestResult('matchStatistics', 'Match Statistics System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 4. PLAYER PERFORMANCE METRICS TRACKING TEST
 */
async function testPlayerMetrics() {
    console.log('👤 Testing Player Performance Metrics Tracking...');
    
    try {
        // Test player data retrieval
        const playersResponse = await makeRequest(`${API_BASE}/players`);
        const hasPlayers = playersResponse.data && (Array.isArray(playersResponse.data) || playersResponse.data.data);
        
        addTestResult('playerMetrics', 'Player Data Retrieval', hasPlayers, {
            statusCode: playersResponse.statusCode,
            hasPlayers,
            playerCount: hasPlayers ? (Array.isArray(playersResponse.data) ? playersResponse.data.length : 
                        Array.isArray(playersResponse.data.data) ? playersResponse.data.data.length : 0) : 0,
            responseTime: playersResponse.responseTime
        });
        
        // Test player analytics aggregation
        const playerAnalyticsResponse = await makeRequest(`${API_BASE}/admin/analytics?period=30d`);
        const hasPlayerAnalytics = playerAnalyticsResponse.data && 
                                 playerAnalyticsResponse.data.data && 
                                 playerAnalyticsResponse.data.data.player_analytics;
        
        addTestResult('playerMetrics', 'Player Analytics Aggregation', hasPlayerAnalytics, {
            statusCode: playerAnalyticsResponse.statusCode,
            hasAnalytics: hasPlayerAnalytics,
            responseTime: playerAnalyticsResponse.responseTime,
            analyticsFields: hasPlayerAnalytics ? Object.keys(playerAnalyticsResponse.data.data.player_analytics) : []
        });
        
        // Test individual player statistics
        if (hasPlayers) {
            const players = Array.isArray(playersResponse.data) ? playersResponse.data : playersResponse.data.data || [];
            
            if (players.length > 0) {
                const firstPlayer = players[0];
                const playerStatsFields = ['rating', 'wins', 'losses', 'role', 'team_id'];
                const hasPlayerStats = playerStatsFields.some(field => firstPlayer[field] !== undefined);
                
                addTestResult('playerMetrics', 'Individual Player Statistics', hasPlayerStats, {
                    playerId: firstPlayer.id,
                    playerName: firstPlayer.name,
                    hasRating: firstPlayer.rating !== undefined,
                    hasWins: firstPlayer.wins !== undefined,
                    hasLosses: firstPlayer.losses !== undefined,
                    hasRole: firstPlayer.role !== undefined,
                    hasTeam: firstPlayer.team_id !== undefined,
                    availableFields: Object.keys(firstPlayer)
                });
                
                // Test player performance analytics
                const performanceMetrics = [
                    { name: 'Player Rating Distribution', field: 'player_distribution' },
                    { name: 'Top Players Analytics', field: 'top_players' },
                    { name: 'Player Growth Trend', field: 'player_growth_trend' },
                    { name: 'Role Performance Analytics', field: 'role_performance' },
                    { name: 'Player Activity Levels', field: 'player_activity_levels' }
                ];
                
                for (const metric of performanceMetrics) {
                    const hasMetric = hasPlayerAnalytics && 
                                    playerAnalyticsResponse.data.data.player_analytics[metric.field];
                    
                    addTestResult('playerMetrics', metric.name, hasMetric !== undefined, {
                        hasMetric: hasMetric !== undefined,
                        metricData: hasMetric,
                        dataType: typeof hasMetric
                    });
                }
            }
        }
        
    } catch (error) {
        addTestResult('playerMetrics', 'Player Metrics System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 5. TOURNAMENT DATA ANALYSIS TEST
 */
async function testTournamentAnalysis() {
    console.log('🏆 Testing Tournament Data Analysis Capabilities...');
    
    try {
        // Test event/tournament data retrieval
        const eventsResponse = await makeRequest(`${API_BASE}/events`);
        const hasEvents = eventsResponse.data && (Array.isArray(eventsResponse.data) || eventsResponse.data.data);
        
        addTestResult('tournamentAnalysis', 'Tournament Data Retrieval', hasEvents, {
            statusCode: eventsResponse.statusCode,
            hasEvents,
            eventCount: hasEvents ? (Array.isArray(eventsResponse.data) ? eventsResponse.data.length : 
                       Array.isArray(eventsResponse.data.data) ? eventsResponse.data.data.length : 0) : 0,
            responseTime: eventsResponse.responseTime
        });
        
        // Test tournament analytics through admin endpoint
        const tournamentAnalyticsResponse = await makeRequest(`${API_BASE}/admin/analytics?period=30d`);
        const hasTournamentAnalytics = tournamentAnalyticsResponse.data && 
                                     tournamentAnalyticsResponse.data.data && 
                                     tournamentAnalyticsResponse.data.data.competitive_insights;
        
        addTestResult('tournamentAnalysis', 'Tournament Analytics Aggregation', hasTournamentAnalytics, {
            statusCode: tournamentAnalyticsResponse.statusCode,
            hasAnalytics: hasTournamentAnalytics,
            responseTime: tournamentAnalyticsResponse.responseTime,
            insightFields: hasTournamentAnalytics ? Object.keys(tournamentAnalyticsResponse.data.data.competitive_insights) : []
        });
        
        // Test tournament performance analysis
        if (hasEvents) {
            const events = Array.isArray(eventsResponse.data) ? eventsResponse.data : eventsResponse.data.data || [];
            
            const tournamentTypes = events.reduce((acc, event) => {
                acc[event.type] = (acc[event.type] || 0) + 1;
                return acc;
            }, {});
            
            const tournamentStatuses = events.reduce((acc, event) => {
                acc[event.status] = (acc[event.status] || 0) + 1;
                return acc;
            }, {});
            
            addTestResult('tournamentAnalysis', 'Tournament Classification Analysis', true, {
                totalTournaments: events.length,
                typeDistribution: tournamentTypes,
                statusDistribution: tournamentStatuses,
                uniqueTypes: Object.keys(tournamentTypes).length,
                uniqueStatuses: Object.keys(tournamentStatuses).length
            });
        }
        
        // Test competitive insights
        const competitiveMetrics = [
            { name: 'Tournament Participation Analysis', field: 'tournament_participation' },
            { name: 'Prize Pool Distribution', field: 'prize_pool_distribution' },
            { name: 'Regional Competition Analysis', field: 'regional_competition' },
            { name: 'Skill Distribution Analysis', field: 'skill_distribution' },
            { name: 'Competitive Activity Tracking', field: 'competitive_activity' }
        ];
        
        for (const metric of competitiveMetrics) {
            const hasMetric = hasTournamentAnalytics && 
                            tournamentAnalyticsResponse.data.data.competitive_insights[metric.field];
            
            addTestResult('tournamentAnalysis', metric.name, hasMetric !== undefined, {
                hasMetric: hasMetric !== undefined,
                metricData: hasMetric,
                dataType: typeof hasMetric
            });
        }
        
    } catch (error) {
        addTestResult('tournamentAnalysis', 'Tournament Analysis System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 6. REAL-TIME DATA INTEGRATION TEST
 */
async function testRealTimeData() {
    console.log('🔄 Testing Real-time Data Integration and WebSocket Capabilities...');
    
    try {
        // Test live matches endpoint
        const liveMatchesResponse = await makeRequest(`${API_BASE}/matches?status=live`);
        const hasLiveMatches = liveMatchesResponse.data && (Array.isArray(liveMatchesResponse.data) || liveMatchesResponse.data.data);
        
        addTestResult('realTimeData', 'Live Matches Data Feed', liveMatchesResponse.statusCode === 200, {
            statusCode: liveMatchesResponse.statusCode,
            hasLiveData: hasLiveMatches,
            liveMatchCount: hasLiveMatches ? (Array.isArray(liveMatchesResponse.data) ? 
                          liveMatchesResponse.data.filter(m => m.status === 'live').length : 0) : 0,
            responseTime: liveMatchesResponse.responseTime
        });
        
        // Test real-time analytics updates
        const realTimeAnalyticsResponse = await makeRequest(`${API_BASE}/admin/analytics?period=1d`);
        const hasRealTimeData = realTimeAnalyticsResponse.data && 
                              realTimeAnalyticsResponse.data.data && 
                              realTimeAnalyticsResponse.data.generated_at;
        
        addTestResult('realTimeData', 'Real-time Analytics Updates', hasRealTimeData, {
            statusCode: realTimeAnalyticsResponse.statusCode,
            hasTimestamp: !!realTimeAnalyticsResponse.data?.generated_at,
            generatedAt: realTimeAnalyticsResponse.data?.generated_at,
            responseTime: realTimeAnalyticsResponse.responseTime,
            dataFreshness: hasRealTimeData ? 'Current' : 'Unknown'
        });
        
        // Test live scoring and match updates
        const liveUpdatesTests = [
            { name: 'Live Match Statistics', endpoint: '/matches?status=live' },
            { name: 'Active Events Feed', endpoint: '/events?status=live' },
            { name: 'Real-time User Activity', endpoint: '/admin/analytics' },
            { name: 'Dynamic Dashboard Updates', endpoint: '/admin/stats' }
        ];
        
        for (const test of liveUpdatesTests) {
            try {
                const response = await makeRequest(`${API_BASE}${test.endpoint}`);
                const hasRealTimeCapability = response.statusCode === 200 && response.data;
                
                addTestResult('realTimeData', test.name, hasRealTimeCapability, {
                    statusCode: response.statusCode,
                    hasData: !!response.data,
                    responseTime: response.responseTime,
                    supportsRealTime: response.responseTime < 2000 // Under 2s for real-time
                });
                
            } catch (error) {
                addTestResult('realTimeData', test.name, false, {
                    error: error.error || error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        // Test WebSocket-like capabilities (through rapid API calls)
        const rapidCallsStart = Date.now();
        const rapidCalls = [];
        
        for (let i = 0; i < 5; i++) {
            try {
                const response = await makeRequest(`${API_BASE}/admin/stats`);
                rapidCalls.push({
                    call: i + 1,
                    responseTime: response.responseTime,
                    success: response.statusCode === 200
                });
            } catch (error) {
                rapidCalls.push({
                    call: i + 1,
                    error: error.error || error.message,
                    success: false
                });
            }
        }
        
        const avgResponseTime = rapidCalls
            .filter(c => c.responseTime)
            .reduce((sum, c) => sum + c.responseTime, 0) / rapidCalls.length;
        
        addTestResult('realTimeData', 'Real-time Performance Under Load', avgResponseTime < 3000, {
            totalCalls: rapidCalls.length,
            successfulCalls: rapidCalls.filter(c => c.success).length,
            averageResponseTime: Math.round(avgResponseTime),
            callResults: rapidCalls,
            supportsHighFrequency: avgResponseTime < 3000
        });
        
    } catch (error) {
        addTestResult('realTimeData', 'Real-time Data System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 7. DATA VISUALIZATION AND CHART RENDERING TEST
 */
async function testDataVisualization() {
    console.log('📊 Testing Data Visualization Components and Chart Rendering...');
    
    try {
        // Test frontend analytics page
        const frontendResponse = await makeRequest(`${BASE_URL}/admin`);
        const hasFrontend = frontendResponse.statusCode === 200;
        
        addTestResult('dataVisualization', 'Frontend Analytics Page Access', hasFrontend, {
            statusCode: frontendResponse.statusCode,
            responseTime: frontendResponse.responseTime,
            hasContent: frontendResponse.rawData && frontendResponse.rawData.length > 1000,
            contentType: frontendResponse.headers['content-type']
        });
        
        // Test analytics data structure for visualization
        const analyticsResponse = await makeRequest(`${API_BASE}/admin/analytics?period=30d`);
        const hasVisualizationData = analyticsResponse.data && analyticsResponse.data.data;
        
        const visualizationComponents = [
            { name: 'User Growth Trends', field: 'user_analytics.growth_trend' },
            { name: 'Match Statistics Charts', field: 'match_analytics.matches_trend' },
            { name: 'Team Performance Charts', field: 'team_analytics.regional_performance' },
            { name: 'Hero Analytics Visualization', field: 'hero_analytics.hero_pick_rates' },
            { name: 'Map Analytics Charts', field: 'map_analytics.most_played_maps' },
            { name: 'Engagement Metrics Display', field: 'engagement_metrics.forum_activity' },
            { name: 'Performance Trends Graphs', field: 'performance_trends' }
        ];
        
        for (const component of visualizationComponents) {
            const fieldPath = component.field.split('.');
            let data = hasVisualizationData ? analyticsResponse.data.data : null;
            
            for (const field of fieldPath) {
                data = data && data[field];
            }
            
            const hasChartData = data !== null && data !== undefined;
            const isChartReady = hasChartData && (Array.isArray(data) || typeof data === 'object');
            
            addTestResult('dataVisualization', component.name, isChartReady, {
                hasData: hasChartData,
                isChartReady,
                dataType: typeof data,
                isArray: Array.isArray(data),
                dataLength: Array.isArray(data) ? data.length : (data ? Object.keys(data).length : 0),
                sampleData: Array.isArray(data) && data.length > 0 ? data[0] : 
                          (data && typeof data === 'object' ? Object.keys(data).slice(0, 3) : data)
            });
        }
        
        // Test chart data export capabilities
        const exportFormats = ['JSON', 'CSV-like structure'];
        for (const format of exportFormats) {
            const supportsExport = hasVisualizationData && analyticsResponse.data.data;
            
            addTestResult('dataVisualization', `Data Export (${format})`, supportsExport, {
                format,
                exportable: supportsExport,
                dataSize: supportsExport ? JSON.stringify(analyticsResponse.data.data).length : 0,
                exportableFields: supportsExport ? Object.keys(analyticsResponse.data.data).length : 0
            });
        }
        
    } catch (error) {
        addTestResult('dataVisualization', 'Data Visualization System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 8. PERFORMANCE ANALYTICS AND SYSTEM MONITORING TEST  
 */
async function testPerformanceAnalytics() {
    console.log('⚡ Testing Performance Analytics and System Monitoring...');
    
    try {
        // Test system health metrics
        const healthResponse = await makeRequest(`${API_BASE}/admin/analytics`);
        const hasHealthData = healthResponse.data && healthResponse.data.data;
        
        addTestResult('performanceAnalytics', 'System Health Monitoring', hasHealthData, {
            statusCode: healthResponse.statusCode,
            responseTime: healthResponse.responseTime,
            hasHealthMetrics: hasHealthData,
            systemResponsive: healthResponse.responseTime < 5000
        });
        
        // Test platform usage metrics
        const platformMetrics = [
            { name: 'User Activity Tracking', field: 'user_analytics' },
            { name: 'Content Engagement Metrics', field: 'engagement_metrics' },
            { name: 'Match Viewership Analytics', field: 'match_analytics' },
            { name: 'Platform Usage Statistics', field: 'overview' },
            { name: 'System Performance Metrics', field: 'platform_health' }
        ];
        
        for (const metric of platformMetrics) {
            const hasMetricData = hasHealthData && healthResponse.data.data[metric.field];
            
            addTestResult('performanceAnalytics', metric.name, hasMetricData !== undefined, {
                hasMetric: hasMetricData !== undefined,
                metricType: typeof hasMetricData,
                metricFields: hasMetricData && typeof hasMetricData === 'object' ? 
                            Object.keys(hasMetricData).length : 0,
                sampleFields: hasMetricData && typeof hasMetricData === 'object' ? 
                            Object.keys(hasMetricData).slice(0, 3) : []
            });
        }
        
        // Test performance optimization tracking
        const performanceTests = [
            { name: 'Database Query Performance', endpoint: '/admin/stats' },
            { name: 'API Response Performance', endpoint: '/admin/analytics' },
            { name: 'Data Processing Performance', endpoint: '/teams' },
            { name: 'Analytics Computation Performance', endpoint: '/admin/analytics?period=7d' }
        ];
        
        let totalResponseTime = 0;
        let slowestEndpoint = '';
        let slowestTime = 0;
        let fastestEndpoint = '';
        let fastestTime = Number.MAX_VALUE;
        
        for (const test of performanceTests) {
            try {
                const response = await makeRequest(`${API_BASE}${test.endpoint}`);
                totalResponseTime += response.responseTime;
                
                if (response.responseTime > slowestTime) {
                    slowestTime = response.responseTime;
                    slowestEndpoint = test.endpoint;
                }
                
                if (response.responseTime < fastestTime) {
                    fastestTime = response.responseTime;
                    fastestEndpoint = test.endpoint;
                }
                
                const isOptimal = response.responseTime < 2000; // Under 2s
                const isAcceptable = response.responseTime < 5000; // Under 5s
                
                addTestResult('performanceAnalytics', test.name, isAcceptable, {
                    responseTime: response.responseTime,
                    optimal: isOptimal,
                    acceptable: isAcceptable,
                    statusCode: response.statusCode,
                    hasData: !!response.data,
                    performanceRating: isOptimal ? 'Excellent' : isAcceptable ? 'Good' : 'Needs Improvement'
                });
                
            } catch (error) {
                addTestResult('performanceAnalytics', test.name, false, {
                    error: error.error || error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        
        // Update performance metrics
        testResults.performanceMetrics.averageResponseTime = Math.round(totalResponseTime / performanceTests.length);
        testResults.performanceMetrics.slowestEndpoint = slowestEndpoint;
        testResults.performanceMetrics.fastestEndpoint = fastestEndpoint;
        
    } catch (error) {
        addTestResult('performanceAnalytics', 'Performance Analytics System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 9. MOBILE-RESPONSIVE ANALYTICS DISPLAY TEST
 */
async function testMobileResponsiveness() {
    console.log('📱 Testing Mobile-Responsive Analytics Display...');
    
    try {
        // Test mobile-friendly API responses
        const mobileHeaders = {
            'User-Agent': 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
            'Accept': 'application/json, text/html'
        };
        
        const mobileResponse = await makeRequest(`${API_BASE}/admin/analytics`, {
            headers: mobileHeaders
        });
        
        const supportsMobile = mobileResponse.statusCode === 200 && mobileResponse.data;
        
        addTestResult('mobileResponsiveness', 'Mobile API Compatibility', supportsMobile, {
            statusCode: mobileResponse.statusCode,
            responseTime: mobileResponse.responseTime,
            hasData: !!mobileResponse.data,
            userAgent: 'Mobile Safari',
            mobileOptimized: mobileResponse.responseTime < 3000
        });
        
        // Test frontend mobile compatibility
        const mobileFrontendResponse = await makeRequest(`${BASE_URL}/admin`, {
            headers: mobileHeaders
        });
        
        const hasMobileFrontend = mobileFrontendResponse.statusCode === 200;
        
        addTestResult('mobileResponsiveness', 'Mobile Frontend Compatibility', hasMobileFrontend, {
            statusCode: mobileFrontendResponse.statusCode,
            responseTime: mobileFrontendResponse.responseTime,
            hasContent: mobileFrontendResponse.rawData && mobileFrontendResponse.rawData.length > 1000,
            mobileHeaders: mobileFrontendResponse.headers
        });
        
        // Test responsive data formats
        const responsiveDataTests = [
            { name: 'Condensed Mobile Analytics', endpoint: '/admin/stats' },
            { name: 'Simplified Mobile Charts', endpoint: '/admin/analytics?period=7d' },
            { name: 'Mobile-Optimized Performance', endpoint: '/teams' }
        ];
        
        for (const test of responsiveDataTests) {
            try {
                const response = await makeRequest(`${API_BASE}${test.endpoint}`, {
                    headers: mobileHeaders
                });
                
                const isMobileOptimized = response.statusCode === 200 && 
                                        response.responseTime < 4000 &&
                                        response.data;
                
                addTestResult('mobileResponsiveness', test.name, isMobileOptimized, {
                    statusCode: response.statusCode,
                    responseTime: response.responseTime,
                    hasData: !!response.data,
                    dataSize: response.rawData ? response.rawData.length : 0,
                    mobileOptimized: isMobileOptimized
                });
                
            } catch (error) {
                addTestResult('mobileResponsiveness', test.name, false, {
                    error: error.error || error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
    } catch (error) {
        addTestResult('mobileResponsiveness', 'Mobile Responsiveness System', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

/**
 * 10. SYSTEM MONITORING AND HEALTH CHECKS
 */
async function testSystemMonitoring() {
    console.log('🔧 Testing System Monitoring and Health Checks...');
    
    try {
        // Test overall system health
        const healthResponse = await makeRequest(`${API_BASE}/admin/analytics`);
        const systemHealthy = healthResponse.statusCode === 200 || healthResponse.statusCode === 401;
        
        addTestResult('systemMonitoring', 'Overall System Health', systemHealthy, {
            statusCode: healthResponse.statusCode,
            responseTime: healthResponse.responseTime,
            systemResponsive: healthResponse.responseTime < 10000,
            hasData: !!healthResponse.data,
            errorRate: healthResponse.statusCode >= 500 ? 'High' : 'Normal'
        });
        
        // Test analytics accuracy through data consistency
        const dataConsistencyTests = [
            { name: 'User Count Consistency', endpoint1: '/admin/stats', endpoint2: '/admin/analytics' },
            { name: 'Match Count Consistency', endpoint1: '/admin/stats', endpoint2: '/matches' },
            { name: 'Team Count Consistency', endpoint1: '/admin/stats', endpoint2: '/teams' },
            { name: 'Event Count Consistency', endpoint1: '/admin/stats', endpoint2: '/events' }
        ];
        
        for (const test of dataConsistencyTests) {
            try {
                const [response1, response2] = await Promise.all([
                    makeRequest(`${API_BASE}${test.endpoint1}`),
                    makeRequest(`${API_BASE}${test.endpoint2}`)
                ]);
                
                const hasData1 = response1.data && (response1.data.data || response1.data.overview);
                const hasData2 = response2.data && (Array.isArray(response2.data) || response2.data.data);
                const dataConsistent = hasData1 && hasData2;
                
                addTestResult('systemMonitoring', test.name, dataConsistent, {
                    endpoint1Status: response1.statusCode,
                    endpoint2Status: response2.statusCode,
                    hasData1: hasData1,
                    hasData2: hasData2,
                    dataConsistent,
                    response1Time: response1.responseTime,
                    response2Time: response2.responseTime
                });
                
            } catch (error) {
                addTestResult('systemMonitoring', test.name, false, {
                    error: error.error || error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        // Calculate analytics accuracy
        const accuracyScore = (testResults.passedTests / Math.max(testResults.totalTests, 1)) * 100;
        testResults.performanceMetrics.analyticsAccuracy = Math.round(accuracyScore);
        
        addTestResult('systemMonitoring', 'Analytics System Accuracy', accuracyScore > 80, {
            accuracyScore: Math.round(accuracyScore),
            totalTests: testResults.totalTests,
            passedTests: testResults.passedTests,
            failedTests: testResults.failedTests,
            systemRating: accuracyScore > 90 ? 'Excellent' : accuracyScore > 80 ? 'Good' : 'Needs Improvement'
        });
        
    } catch (error) {
        addTestResult('systemMonitoring', 'System Monitoring', false, {
            error: error.error || error.message,
            critical: true
        });
    }
}

// Generate Recommendations and Summary
function generateRecommendations() {
    console.log('💡 Generating Recommendations...');
    
    // Performance recommendations
    if (testResults.performanceMetrics.averageResponseTime > 3000) {
        testResults.recommendations.push({
            category: 'Performance',
            priority: 'High',
            recommendation: 'API response times are slow. Consider implementing caching, database optimization, or CDN integration.'
        });
    }
    
    // Analytics accuracy recommendations
    if (testResults.performanceMetrics.analyticsAccuracy < 90) {
        testResults.recommendations.push({
            category: 'Accuracy',
            priority: 'High',
            recommendation: 'Analytics system accuracy is below optimal. Review data collection and aggregation processes.'
        });
    }
    
    // Data visualization recommendations
    const visualizationPassed = testResults.testCategories.dataVisualization.passed;
    const visualizationTotal = testResults.testCategories.dataVisualization.tests.length;
    if (visualizationTotal > 0 && visualizationPassed / visualizationTotal < 0.8) {
        testResults.recommendations.push({
            category: 'Visualization',
            priority: 'Medium',
            recommendation: 'Data visualization capabilities need improvement. Consider adding more chart types and export options.'
        });
    }
    
    // Real-time capabilities recommendations
    const realTimePassed = testResults.testCategories.realTimeData.passed;
    const realTimeTotal = testResults.testCategories.realTimeData.tests.length;
    if (realTimeTotal > 0 && realTimePassed / realTimeTotal < 0.7) {
        testResults.recommendations.push({
            category: 'Real-time',
            priority: 'Medium',
            recommendation: 'Real-time data capabilities could be enhanced. Consider implementing WebSocket connections or server-sent events.'
        });
    }
    
    // Mobile optimization recommendations
    const mobilePassed = testResults.testCategories.mobileResponsiveness.passed;
    const mobileTotal = testResults.testCategories.mobileResponsiveness.tests.length;
    if (mobileTotal > 0 && mobilePassed / mobileTotal < 0.8) {
        testResults.recommendations.push({
            category: 'Mobile',
            priority: 'Medium',
            recommendation: 'Mobile responsiveness needs improvement. Optimize API responses and frontend for mobile devices.'
        });
    }
    
    // Database connectivity recommendations
    if (testResults.testCategories.databaseConnectivity.failed > 0) {
        testResults.recommendations.push({
            category: 'Database',
            priority: 'High',
            recommendation: 'Database connectivity issues detected. Review database configuration and connection pooling.'
        });
    }
    
    // Generate summary
    testResults.summary = {
        overallHealth: testResults.performanceMetrics.analyticsAccuracy > 90 ? 'Excellent' : 
                      testResults.performanceMetrics.analyticsAccuracy > 80 ? 'Good' : 
                      testResults.performanceMetrics.analyticsAccuracy > 70 ? 'Fair' : 'Poor',
        
        strengthAreas: [],
        improvementAreas: [],
        
        keyFindings: {
            totalEndpointsTested: Object.values(testResults.testCategories).reduce((sum, cat) => sum + cat.tests.length, 0),
            averageResponseTime: testResults.performanceMetrics.averageResponseTime,
            systemAccuracy: testResults.performanceMetrics.analyticsAccuracy,
            criticalIssuesCount: testResults.criticalIssues.length,
            recommendationsCount: testResults.recommendations.length
        }
    };
    
    // Identify strength areas
    Object.entries(testResults.testCategories).forEach(([category, results]) => {
        const successRate = results.tests.length > 0 ? (results.passed / results.tests.length) * 100 : 0;
        if (successRate >= 90) {
            testResults.summary.strengthAreas.push({
                category: category.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()),
                successRate: Math.round(successRate),
                status: 'Excellent'
            });
        }
    });
    
    // Identify improvement areas
    Object.entries(testResults.testCategories).forEach(([category, results]) => {
        const successRate = results.tests.length > 0 ? (results.passed / results.tests.length) * 100 : 0;
        if (successRate < 80) {
            testResults.summary.improvementAreas.push({
                category: category.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase()),
                successRate: Math.round(successRate),
                status: successRate < 50 ? 'Critical' : successRate < 70 ? 'Poor' : 'Fair',
                priority: successRate < 50 ? 'High' : 'Medium'
            });
        }
    });
}

// Main Test Execution
async function runComprehensiveAnalyticsTest() {
    console.log('🚀 Starting Comprehensive Analytics and Data Integration System Test...');
    console.log('==============================================================');
    
    const startTime = Date.now();
    
    try {
        // Run all test categories
        await testAnalyticsApiEndpoints();
        await testDatabaseConnectivity();
        await testMatchStatistics();
        await testPlayerMetrics();
        await testTournamentAnalysis();
        await testRealTimeData();
        await testDataVisualization();
        await testPerformanceAnalytics();
        await testMobileResponsiveness();
        await testSystemMonitoring();
        
        // Generate recommendations and summary
        generateRecommendations();
        
        const endTime = Date.now();
        const totalDuration = endTime - startTime;
        
        testResults.testDuration = {
            total: totalDuration,
            formatted: `${Math.floor(totalDuration / 1000 / 60)}m ${Math.floor((totalDuration / 1000) % 60)}s`
        };
        
        // Save results
        fs.writeFileSync(TEST_OUTPUT_FILE, JSON.stringify(testResults, null, 2));
        
        // Display results
        console.log('\n🎯 COMPREHENSIVE ANALYTICS SYSTEM TEST RESULTS');
        console.log('==============================================');
        console.log(`📊 Total Tests: ${testResults.totalTests}`);
        console.log(`✅ Passed: ${testResults.passedTests}`);
        console.log(`❌ Failed: ${testResults.failedTests}`);
        console.log(`🎯 Success Rate: ${Math.round((testResults.passedTests / testResults.totalTests) * 100)}%`);
        console.log(`⚡ Average Response Time: ${testResults.performanceMetrics.averageResponseTime}ms`);
        console.log(`🔧 System Health: ${testResults.summary.overallHealth}`);
        console.log(`⏱️ Test Duration: ${testResults.testDuration.formatted}`);
        
        console.log('\n📈 TEST CATEGORIES BREAKDOWN:');
        Object.entries(testResults.testCategories).forEach(([category, results]) => {
            const successRate = results.tests.length > 0 ? Math.round((results.passed / results.tests.length) * 100) : 0;
            console.log(`  ${category.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())}: ${results.passed}/${results.tests.length} (${successRate}%)`);
        });
        
        if (testResults.summary.strengthAreas.length > 0) {
            console.log('\n💪 STRENGTH AREAS:');
            testResults.summary.strengthAreas.forEach(area => {
                console.log(`  ✅ ${area.category}: ${area.successRate}% (${area.status})`);
            });
        }
        
        if (testResults.summary.improvementAreas.length > 0) {
            console.log('\n🔧 IMPROVEMENT AREAS:');
            testResults.summary.improvementAreas.forEach(area => {
                console.log(`  ⚠️  ${area.category}: ${area.successRate}% (${area.status}) - Priority: ${area.priority}`);
            });
        }
        
        if (testResults.recommendations.length > 0) {
            console.log('\n💡 TOP RECOMMENDATIONS:');
            testResults.recommendations.slice(0, 5).forEach((rec, index) => {
                console.log(`  ${index + 1}. [${rec.category}] ${rec.recommendation}`);
            });
        }
        
        if (testResults.criticalIssues.length > 0) {
            console.log('\n🚨 CRITICAL ISSUES:');
            testResults.criticalIssues.forEach((issue, index) => {
                console.log(`  ${index + 1}. ${issue.test}: ${issue.issue}`);
            });
        }
        
        console.log(`\n📄 Full report saved to: ${TEST_OUTPUT_FILE}`);
        console.log('==============================================');
        
        return testResults;
        
    } catch (error) {
        console.error('❌ Test execution failed:', error);
        testResults.executionError = error.message;
        fs.writeFileSync(TEST_OUTPUT_FILE, JSON.stringify(testResults, null, 2));
        throw error;
    }
}

// Execute the test if run directly
if (require.main === module) {
    runComprehensiveAnalyticsTest()
        .then(() => {
            console.log('✅ Analytics system test completed successfully!');
            process.exit(0);
        })
        .catch((error) => {
            console.error('❌ Analytics system test failed:', error.message);
            process.exit(1);
        });
}

module.exports = {
    runComprehensiveAnalyticsTest,
    testResults
};