const axios = require('axios');
const EventSource = require('eventsource');

const BASE_URL = 'http://localhost:8000/api';

// Test configuration
const config = {
    timeout: 10000,
    validateStatus: function (status) {
        return status >= 200 && status < 500; // Accept any status for testing
    }
};

// Test results tracking
let testResults = {
    sseConnection: { status: 'pending', details: null },
    createMatch: { status: 'pending', details: null },
    liveControl: { status: 'pending', details: null },
    updateStats: { status: 'pending', details: null }
};

// Admin authentication (if needed)
let authToken = null;
let testMatchId = null;

async function authenticate() {
    console.log('🔐 Attempting authentication...');
    try {
        // Try to get an admin token - check if there's an existing admin user
        const response = await axios.post(`${BASE_URL}/auth/login`, {
            email: 'admin@mrvl.gg',
            password: 'admin123'
        }, config);
        
        if (response.data && response.data.access_token) {
            authToken = response.data.access_token;
            console.log('✅ Authentication successful');
            return true;
        }
    } catch (error) {
        console.log('❌ Authentication failed:', error.response?.status || error.message);
        
        // Try alternate credentials
        try {
            const altResponse = await axios.post(`${BASE_URL}/auth/login`, {
                email: 'admin@example.com',
                password: 'password'
            }, config);
            
            if (altResponse.data && altResponse.data.access_token) {
                authToken = altResponse.data.access_token;
                console.log('✅ Authentication successful with alternate credentials');
                return true;
            }
        } catch (altError) {
            console.log('❌ Alternate authentication failed:', altError.response?.status || altError.message);
        }
    }
    return false;
}

async function testSSEConnection() {
    console.log('\n📡 Testing SSE Connection: GET /api/live-updates/2/stream');
    
    return new Promise((resolve) => {
        try {
            const eventSource = new EventSource(`${BASE_URL}/live-updates/2/stream`);
            let connected = false;
            
            const timeout = setTimeout(() => {
                if (!connected) {
                    eventSource.close();
                    testResults.sseConnection = {
                        status: 'failed',
                        details: 'Connection timeout after 5 seconds'
                    };
                    console.log('❌ SSE Connection: TIMEOUT');
                    resolve();
                }
            }, 5000);
            
            eventSource.onopen = () => {
                connected = true;
                clearTimeout(timeout);
                eventSource.close();
                testResults.sseConnection = {
                    status: 'success',
                    details: 'SSE connection established successfully'
                };
                console.log('✅ SSE Connection: SUCCESS');
                resolve();
            };
            
            eventSource.onerror = (error) => {
                clearTimeout(timeout);
                eventSource.close();
                testResults.sseConnection = {
                    status: 'failed',
                    details: `SSE connection error: ${error.message || 'Unknown error'}`
                };
                console.log('❌ SSE Connection: ERROR');
                resolve();
            };
            
            eventSource.onmessage = (event) => {
                console.log('📨 Received SSE message:', event.data);
            };
            
        } catch (error) {
            testResults.sseConnection = {
                status: 'failed',
                details: `Exception: ${error.message}`
            };
            console.log('❌ SSE Connection: EXCEPTION -', error.message);
            resolve();
        }
    });
}

async function testCreateMatch() {
    console.log('\n🆕 Testing Match Creation: POST /api/admin/matches');
    
    const matchData = {
        team1_id: 1,
        team2_id: 2,
        event_id: 1,
        match_type: 'tournament',
        scheduled_at: new Date().toISOString(),
        best_of: 3,
        status: 'upcoming'
    };
    
    const headers = authToken ? { 'Authorization': `Bearer ${authToken}` } : {};
    
    try {
        const response = await axios.post(`${BASE_URL}/admin/matches`, matchData, {
            ...config,
            headers
        });
        
        if (response.status === 201 && response.data) {
            testMatchId = response.data.id || response.data.match?.id || 2; // Fallback to 2
            testResults.createMatch = {
                status: 'success',
                details: `Match created successfully (ID: ${testMatchId}), Status: ${response.status}`
            };
            console.log('✅ Match Creation: SUCCESS - Status:', response.status);
        } else {
            testResults.createMatch = {
                status: 'partial',
                details: `Unexpected response format, Status: ${response.status}`
            };
            console.log('⚠️ Match Creation: PARTIAL - Status:', response.status);
        }
    } catch (error) {
        const status = error.response?.status;
        const message = error.response?.data?.message || error.message;
        
        if (status === 401) {
            testResults.createMatch = {
                status: 'auth_required',
                details: `Authentication required, Status: ${status}`
            };
            console.log('🔐 Match Creation: AUTH REQUIRED - Status:', status);
        } else if (status === 422) {
            testResults.createMatch = {
                status: 'validation_error',
                details: `Validation error, Status: ${status}, Message: ${message}`
            };
            console.log('❌ Match Creation: VALIDATION ERROR - Status:', status);
        } else {
            testResults.createMatch = {
                status: 'failed',
                details: `Error: ${message}, Status: ${status || 'N/A'}`
            };
            console.log('❌ Match Creation: FAILED - Status:', status, 'Message:', message);
        }
    }
}

async function testLiveControl() {
    console.log('\n🎮 Testing Live Control: PUT /api/admin/matches/2/live-control');
    
    const controlData = {
        action: 'start',
        map_id: 1,
        additional_data: {
            round: 1,
            timestamp: new Date().toISOString()
        }
    };
    
    const headers = authToken ? { 'Authorization': `Bearer ${authToken}` } : {};
    const matchId = testMatchId || 2;
    
    try {
        const response = await axios.put(`${BASE_URL}/admin/matches/${matchId}/live-control`, controlData, {
            ...config,
            headers
        });
        
        if (response.status >= 200 && response.status < 300) {
            testResults.liveControl = {
                status: 'success',
                details: `Live control executed successfully, Status: ${response.status}`
            };
            console.log('✅ Live Control: SUCCESS - Status:', response.status);
        } else {
            testResults.liveControl = {
                status: 'partial',
                details: `Unexpected response, Status: ${response.status}`
            };
            console.log('⚠️ Live Control: PARTIAL - Status:', response.status);
        }
    } catch (error) {
        const status = error.response?.status;
        const message = error.response?.data?.message || error.message;
        
        if (status === 401) {
            testResults.liveControl = {
                status: 'auth_required',
                details: `Authentication required, Status: ${status}`
            };
            console.log('🔐 Live Control: AUTH REQUIRED - Status:', status);
        } else if (status === 404) {
            testResults.liveControl = {
                status: 'not_found',
                details: `Match not found, Status: ${status}`
            };
            console.log('❌ Live Control: MATCH NOT FOUND - Status:', status);
        } else {
            testResults.liveControl = {
                status: 'failed',
                details: `Error: ${message}, Status: ${status || 'N/A'}`
            };
            console.log('❌ Live Control: FAILED - Status:', status, 'Message:', message);
        }
    }
}

async function testUpdateStats() {
    console.log('\n📊 Testing Stats Update: POST /api/admin/matches/2/update-live-stats');
    
    const statsData = {
        player_stats: [
            {
                player_id: 1,
                kills: 15,
                deaths: 8,
                damage_dealt: 2500,
                healing_done: 1200,
                hero_id: 1
            },
            {
                player_id: 2,
                kills: 12,
                deaths: 10,
                damage_dealt: 2100,
                healing_done: 800,
                hero_id: 2
            }
        ],
        map_stats: {
            map_id: 1,
            duration: 450,
            winner: 'team1'
        },
        match_stats: {
            current_map: 1,
            team1_score: 1,
            team2_score: 0
        }
    };
    
    const headers = authToken ? { 'Authorization': `Bearer ${authToken}` } : {};
    const matchId = testMatchId || 2;
    
    try {
        const response = await axios.post(`${BASE_URL}/admin/matches/${matchId}/update-live-stats`, statsData, {
            ...config,
            headers
        });
        
        if (response.status >= 200 && response.status < 300) {
            testResults.updateStats = {
                status: 'success',
                details: `Stats updated successfully, Status: ${response.status}`
            };
            console.log('✅ Stats Update: SUCCESS - Status:', response.status);
        } else {
            testResults.updateStats = {
                status: 'partial',
                details: `Unexpected response, Status: ${response.status}`
            };
            console.log('⚠️ Stats Update: PARTIAL - Status:', response.status);
        }
    } catch (error) {
        const status = error.response?.status;
        const message = error.response?.data?.message || error.message;
        
        if (status === 401) {
            testResults.updateStats = {
                status: 'auth_required',
                details: `Authentication required, Status: ${status}`
            };
            console.log('🔐 Stats Update: AUTH REQUIRED - Status:', status);
        } else if (status === 404) {
            testResults.updateStats = {
                status: 'not_found',
                details: `Match not found, Status: ${status}`
            };
            console.log('❌ Stats Update: MATCH NOT FOUND - Status:', status);
        } else if (status === 422) {
            testResults.updateStats = {
                status: 'validation_error',
                details: `Validation error, Status: ${status}, Message: ${message}`
            };
            console.log('❌ Stats Update: VALIDATION ERROR - Status:', status);
        } else {
            testResults.updateStats = {
                status: 'failed',
                details: `Error: ${message}, Status: ${status || 'N/A'}`
            };
            console.log('❌ Stats Update: FAILED - Status:', status, 'Message:', message);
        }
    }
}

function generateReport() {
    console.log('\n' + '='.repeat(60));
    console.log('📋 LIVE SCORING ENDPOINTS TEST REPORT');
    console.log('='.repeat(60));
    
    const tests = [
        { name: '1. SSE Connection (GET /api/live-updates/2/stream)', result: testResults.sseConnection },
        { name: '2. Create Match (POST /api/admin/matches)', result: testResults.createMatch },
        { name: '3. Live Control (PUT /api/admin/matches/2/live-control)', result: testResults.liveControl },
        { name: '4. Update Stats (POST /api/admin/matches/2/update-live-stats)', result: testResults.updateStats }
    ];
    
    tests.forEach(test => {
        const status = test.result.status;
        let statusIcon = '❓';
        
        switch(status) {
            case 'success': statusIcon = '✅'; break;
            case 'partial': statusIcon = '⚠️'; break;
            case 'failed': statusIcon = '❌'; break;
            case 'auth_required': statusIcon = '🔐'; break;
            case 'not_found': statusIcon = '🔍'; break;
            case 'validation_error': statusIcon = '📝'; break;
            case 'pending': statusIcon = '⏳'; break;
        }
        
        console.log(`${statusIcon} ${test.name}`);
        console.log(`   Status: ${status.toUpperCase()}`);
        console.log(`   Details: ${test.result.details || 'No details available'}`);
        console.log('');
    });
    
    // Summary
    const successCount = Object.values(testResults).filter(r => r.status === 'success').length;
    const partialCount = Object.values(testResults).filter(r => r.status === 'partial').length;
    const totalTests = Object.keys(testResults).length;
    
    console.log('📊 SUMMARY:');
    console.log(`   Total Tests: ${totalTests}`);
    console.log(`   Successful: ${successCount}`);
    console.log(`   Partial Success: ${partialCount}`);
    console.log(`   Issues: ${totalTests - successCount - partialCount}`);
    
    const report = {
        timestamp: new Date().toISOString(),
        testResults,
        summary: {
            totalTests,
            successful: successCount,
            partialSuccess: partialCount,
            issues: totalTests - successCount - partialCount
        }
    };
    
    // Save report to file
    const fs = require('fs');
    const reportPath = `live_scoring_test_report_${Date.now()}.json`;
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`\n💾 Detailed report saved to: ${reportPath}`);
    
    return report;
}

async function runAllTests() {
    console.log('🚀 Starting Live Scoring Endpoints Test Suite');
    console.log('Testing endpoints for Marvel Rivals Live Scoring System\n');
    
    // Try authentication first
    await authenticate();
    
    // Run all tests
    await testSSEConnection();
    await testCreateMatch();
    await testLiveControl();
    await testUpdateStats();
    
    // Generate final report
    const report = generateReport();
    
    return report;
}

// Run the tests
if (require.main === module) {
    runAllTests().then(() => {
        console.log('\n✨ Test suite completed!');
        process.exit(0);
    }).catch(error => {
        console.error('❌ Test suite failed:', error);
        process.exit(1);
    });
}

module.exports = { runAllTests, testResults };