#!/usr/bin/env node

/**
 * COMPREHENSIVE PLAYER MANAGEMENT SYSTEM TEST
 * Marvel Rivals Tournament Platform
 * 
 * Tests ALL player CRUD operations and field updates
 */

const https = require('https');
const http = require('http');

// Test Configuration
const CONFIG = {
    baseUrl: 'http://localhost:8000',
    apiPrefix: '/api',
    testTimeout: 30000,
    adminCredentials: {
        email: 'test-admin@mrvl.gg',
        password: 'testpassword123'
    }
};

let authToken = null;
let testResults = {
    total: 0,
    passed: 0,
    failed: 0,
    errors: [],
    results: []
};

// HTTP Client with timeout support
function makeRequest(options, data = null) {
    return new Promise((resolve, reject) => {
        const client = options.protocol === 'https:' ? https : http;
        
        const req = client.request(options, (res) => {
            let body = '';
            
            res.on('data', (chunk) => {
                body += chunk;
            });
            
            res.on('end', () => {
                try {
                    const responseData = body ? JSON.parse(body) : {};
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        data: responseData
                    });
                } catch (e) {
                    resolve({
                        statusCode: res.statusCode,
                        headers: res.headers,
                        data: { raw: body }
                    });
                }
            });
        });
        
        req.on('error', reject);
        req.setTimeout(CONFIG.testTimeout, () => {
            req.destroy();
            reject(new Error('Request timeout'));
        });
        
        if (data) {
            req.write(JSON.stringify(data));
        }
        
        req.end();
    });
}

// Helper function to make API calls
async function apiCall(method, endpoint, data = null, options = {}) {
    const url = new URL(`${CONFIG.baseUrl}${CONFIG.apiPrefix}${endpoint}`);
    
    const requestOptions = {
        hostname: url.hostname,
        port: url.port || (url.protocol === 'https:' ? 443 : 80),
        path: url.pathname + url.search,
        method: method.toUpperCase(),
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'User-Agent': 'MRVL-Player-Test/1.0',
            ...options.headers
        }
    };
    
    if (authToken) {
        requestOptions.headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    if (data) {
        requestOptions.headers['Content-Length'] = Buffer.byteLength(JSON.stringify(data));
    }
    
    return makeRequest(requestOptions, data);
}

// Test runner
function runTest(name, testFn) {
    return new Promise(async (resolve) => {
        testResults.total++;
        console.log(`\n🧪 Running: ${name}`);
        
        try {
            const result = await testFn();
            if (result.success) {
                testResults.passed++;
                console.log(`✅ PASSED: ${name}`);
            } else {
                testResults.failed++;
                console.log(`❌ FAILED: ${name} - ${result.message}`);
                testResults.errors.push(`${name}: ${result.message}`);
            }
            testResults.results.push({ name, ...result });
        } catch (error) {
            testResults.failed++;
            const errorMsg = error.message || 'Unknown error';
            console.log(`💥 ERROR: ${name} - ${errorMsg}`);
            testResults.errors.push(`${name}: ${errorMsg}`);
            testResults.results.push({ name, success: false, message: errorMsg });
        }
        
        resolve();
    });
}

// Authentication
async function authenticate() {
    console.log('\n🔐 Authenticating admin user...');
    
    try {
        const response = await apiCall('POST', '/auth/login', CONFIG.adminCredentials);
        
        if (response.statusCode === 200 && (response.data.access_token || response.data.token)) {
            authToken = response.data.access_token || response.data.token;
            console.log('✅ Authentication successful');
            return true;
        } else {
            console.log('❌ Authentication failed:', response.data);
            return false;
        }
    } catch (error) {
        console.log('💥 Authentication error:', error.message);
        return false;
    }
}

// Test 1: Check Player Admin Routes Existence
async function testPlayerAdminRoutes() {
    const endpoints = [
        { method: 'GET', path: '/admin/players', description: 'List all players' },
        { method: 'POST', path: '/admin/players', description: 'Create player' },
        { method: 'GET', path: '/admin/players/1', description: 'Get specific player' },
        { method: 'PUT', path: '/admin/players/1', description: 'Update player' },
        { method: 'DELETE', path: '/admin/players/1', description: 'Delete player' }
    ];
    
    let routeResults = [];
    
    for (const endpoint of endpoints) {
        try {
            const response = await apiCall(endpoint.method, endpoint.path);
            const exists = response.statusCode !== 404;
            routeResults.push({
                ...endpoint,
                exists,
                statusCode: response.statusCode
            });
        } catch (error) {
            routeResults.push({
                ...endpoint,
                exists: false,
                error: error.message
            });
        }
    }
    
    const existingRoutes = routeResults.filter(r => r.exists).length;
    const totalRoutes = routeResults.length;
    
    return {
        success: existingRoutes > 0,
        message: `${existingRoutes}/${totalRoutes} player admin routes exist`,
        details: routeResults
    };
}

// Test 2: Fetch All Players
async function testFetchAllPlayers() {
    try {
        const response = await apiCall('GET', '/admin/players');
        
        if (response.statusCode === 200) {
            const players = response.data.data || response.data || [];
            return {
                success: true,
                message: `Successfully fetched ${Array.isArray(players) ? players.length : 0} players`,
                details: {
                    statusCode: response.statusCode,
                    playerCount: Array.isArray(players) ? players.length : 0,
                    samplePlayer: Array.isArray(players) && players.length > 0 ? players[0] : null
                }
            };
        } else {
            return {
                success: false,
                message: `Failed to fetch players: HTTP ${response.statusCode}`,
                details: response.data
            };
        }
    } catch (error) {
        return {
            success: false,
            message: `Error fetching players: ${error.message}`
        };
    }
}

// Test 3: Create New Player
async function testCreatePlayer() {
    const newPlayer = {
        username: 'test_player_' + Date.now(),
        ign: 'TestGamer',
        real_name: 'Test Player',
        name: 'Test Player',
        role: 'DPS',
        main_hero: 'Spider-Man',
        country: 'US',
        age: 25,
        rating: 2500,
        description: 'Test player for system validation'
    };
    
    try {
        const response = await apiCall('POST', '/admin/players', newPlayer);
        
        if (response.statusCode === 200 || response.statusCode === 201) {
            const createdPlayer = response.data.data || response.data;
            return {
                success: true,
                message: 'Successfully created player',
                details: {
                    statusCode: response.statusCode,
                    playerId: createdPlayer?.id,
                    playerData: createdPlayer
                }
            };
        } else {
            return {
                success: false,
                message: `Failed to create player: HTTP ${response.statusCode}`,
                details: response.data
            };
        }
    } catch (error) {
        return {
            success: false,
            message: `Error creating player: ${error.message}`
        };
    }
}

// Test 4: Update Player Fields
async function testUpdatePlayerFields() {
    // First, get an existing player to update
    try {
        const listResponse = await apiCall('GET', '/admin/players');
        const players = listResponse.data.data || listResponse.data || [];
        
        if (!Array.isArray(players) || players.length === 0) {
            return {
                success: false,
                message: 'No players available to test updates'
            };
        }
        
        const testPlayer = players[0];
        const playerId = testPlayer.id;
        
        // Test updating different field categories
        const fieldTests = [
            {
                category: 'Basic Info',
                fields: {
                    username: 'updated_player_' + Date.now(),
                    real_name: 'Updated Player Name',
                    age: 26
                }
            },
            {
                category: 'Game Info',
                fields: {
                    role: 'Tank',
                    main_hero: 'Doctor Doom',
                    rating: 3000
                }
            },
            {
                category: 'Location Info',
                fields: {
                    country: 'CA',
                    region: 'North America'
                }
            },
            {
                category: 'Team Info',
                fields: {
                    team_id: null // Free agent
                }
            }
        ];
        
        let updateResults = [];
        
        for (const test of fieldTests) {
            try {
                const response = await apiCall('PUT', `/admin/players/${playerId}`, test.fields);
                updateResults.push({
                    category: test.category,
                    success: response.statusCode === 200,
                    statusCode: response.statusCode,
                    fields: Object.keys(test.fields),
                    response: response.data
                });
            } catch (error) {
                updateResults.push({
                    category: test.category,
                    success: false,
                    error: error.message,
                    fields: Object.keys(test.fields)
                });
            }
        }
        
        const successfulUpdates = updateResults.filter(r => r.success).length;
        const totalUpdates = updateResults.length;
        
        return {
            success: successfulUpdates > 0,
            message: `${successfulUpdates}/${totalUpdates} field categories updated successfully`,
            details: {
                playerId,
                updateResults,
                updatableFields: updateResults.filter(r => r.success).flatMap(r => r.fields)
            }
        };
        
    } catch (error) {
        return {
            success: false,
            message: `Error testing field updates: ${error.message}`
        };
    }
}

// Test 5: Test Player-Team Relationships
async function testPlayerTeamRelationships() {
    try {
        // Get teams first
        const teamsResponse = await apiCall('GET', '/admin/teams');
        const teams = teamsResponse.data.data || teamsResponse.data || [];
        
        if (!Array.isArray(teams) || teams.length === 0) {
            return {
                success: false,
                message: 'No teams available to test relationships'
            };
        }
        
        // Get players
        const playersResponse = await apiCall('GET', '/admin/players');
        const players = playersResponse.data.data || playersResponse.data || [];
        
        if (!Array.isArray(players) || players.length === 0) {
            return {
                success: false,
                message: 'No players available to test relationships'
            };
        }
        
        const testPlayer = players[0];
        const testTeam = teams[0];
        
        // Test assigning player to team
        const assignResponse = await apiCall('PUT', `/admin/players/${testPlayer.id}`, {
            team_id: testTeam.id
        });
        
        // Test removing player from team (free agent)
        const removeResponse = await apiCall('PUT', `/admin/players/${testPlayer.id}`, {
            team_id: null
        });
        
        return {
            success: assignResponse.statusCode === 200,
            message: `Team assignment: ${assignResponse.statusCode === 200 ? 'SUCCESS' : 'FAILED'}, ` +
                    `Free agent: ${removeResponse.statusCode === 200 ? 'SUCCESS' : 'FAILED'}`,
            details: {
                assignResult: {
                    statusCode: assignResponse.statusCode,
                    success: assignResponse.statusCode === 200
                },
                removeResult: {
                    statusCode: removeResponse.statusCode,
                    success: removeResponse.statusCode === 200
                },
                testPlayer: testPlayer.id,
                testTeam: testTeam.id
            }
        };
        
    } catch (error) {
        return {
            success: false,
            message: `Error testing team relationships: ${error.message}`
        };
    }
}

// Test 6: Bulk Operations
async function testBulkOperations() {
    try {
        // Get players for bulk operations
        const response = await apiCall('GET', '/admin/players');
        const players = response.data.data || response.data || [];
        
        if (!Array.isArray(players) || players.length < 2) {
            return {
                success: false,
                message: 'Need at least 2 players for bulk operations test'
            };
        }
        
        // Test bulk delete (be careful - use non-essential players)
        const testPlayerIds = players.slice(-2).map(p => p.id); // Last 2 players
        
        const bulkDeleteResponse = await apiCall('POST', '/admin/players/bulk-delete', {
            player_ids: testPlayerIds
        });
        
        return {
            success: bulkDeleteResponse.statusCode === 200,
            message: `Bulk delete: ${bulkDeleteResponse.statusCode === 200 ? 'SUCCESS' : 'FAILED'}`,
            details: {
                statusCode: bulkDeleteResponse.statusCode,
                testedPlayerIds: testPlayerIds,
                response: bulkDeleteResponse.data
            }
        };
        
    } catch (error) {
        return {
            success: false,
            message: `Error testing bulk operations: ${error.message}`
        };
    }
}

// Test 7: Field Validation
async function testFieldValidation() {
    const validationTests = [
        {
            name: 'Invalid Role',
            data: { role: 'InvalidRole' },
            expectFail: true
        },
        {
            name: 'Invalid Age',
            data: { age: 150 },
            expectFail: true
        },
        {
            name: 'Invalid Rating',
            data: { rating: 10000 },
            expectFail: true
        },
        {
            name: 'Valid Data',
            data: {
                username: 'valid_player',
                role: 'DPS',
                age: 20,
                rating: 2000
            },
            expectFail: false
        }
    ];
    
    let validationResults = [];
    
    for (const test of validationTests) {
        try {
            const response = await apiCall('POST', '/admin/players', test.data);
            const failed = response.statusCode >= 400;
            
            validationResults.push({
                name: test.name,
                expectedToFail: test.expectFail,
                actuallyFailed: failed,
                correct: test.expectFail === failed,
                statusCode: response.statusCode
            });
        } catch (error) {
            validationResults.push({
                name: test.name,
                expectedToFail: test.expectFail,
                actuallyFailed: true,
                correct: test.expectFail,
                error: error.message
            });
        }
    }
    
    const correctValidations = validationResults.filter(r => r.correct).length;
    const totalValidations = validationResults.length;
    
    return {
        success: correctValidations === totalValidations,
        message: `${correctValidations}/${totalValidations} validation tests correct`,
        details: validationResults
    };
}

// Test 8: Admin UI Components
async function testAdminUIAvailability() {
    const uiTests = [
        {
            name: 'AdminPlayers Component',
            path: '/var/www/mrvl-frontend/frontend/src/components/admin/AdminPlayers.js'
        }
    ];
    
    const fs = require('fs');
    let uiResults = [];
    
    for (const test of uiTests) {
        try {
            const exists = fs.existsSync(test.path);
            uiResults.push({
                name: test.name,
                exists,
                path: test.path
            });
        } catch (error) {
            uiResults.push({
                name: test.name,
                exists: false,
                error: error.message
            });
        }
    }
    
    const existingComponents = uiResults.filter(r => r.exists).length;
    
    return {
        success: existingComponents > 0,
        message: `${existingComponents}/${uiResults.length} admin UI components exist`,
        details: uiResults
    };
}

// Main test execution
async function runAllTests() {
    console.log('🚀 Starting Comprehensive Player Management System Test');
    console.log('='.repeat(60));
    
    const startTime = Date.now();
    
    // Authentication
    const authSuccess = await authenticate();
    if (!authSuccess) {
        console.log('\n💥 CRITICAL: Authentication failed. Cannot run tests.');
        return;
    }
    
    // Run all tests
    await runTest('Player Admin Routes Existence', testPlayerAdminRoutes);
    await runTest('Fetch All Players', testFetchAllPlayers);
    await runTest('Create New Player', testCreatePlayer);
    await runTest('Update Player Fields', testUpdatePlayerFields);
    await runTest('Player-Team Relationships', testPlayerTeamRelationships);
    await runTest('Bulk Operations', testBulkOperations);
    await runTest('Field Validation', testFieldValidation);
    await runTest('Admin UI Availability', testAdminUIAvailability);
    
    // Generate summary
    const endTime = Date.now();
    const duration = (endTime - startTime) / 1000;
    
    console.log('\n' + '='.repeat(60));
    console.log('📊 TEST SUMMARY');
    console.log('='.repeat(60));
    console.log(`Total Tests: ${testResults.total}`);
    console.log(`Passed: ${testResults.passed} ✅`);
    console.log(`Failed: ${testResults.failed} ❌`);
    console.log(`Success Rate: ${((testResults.passed / testResults.total) * 100).toFixed(1)}%`);
    console.log(`Duration: ${duration.toFixed(2)}s`);
    
    if (testResults.errors.length > 0) {
        console.log('\n🔍 ERRORS:');
        testResults.errors.forEach(error => console.log(`  • ${error}`));
    }
    
    // Generate detailed report
    const report = {
        summary: {
            total: testResults.total,
            passed: testResults.passed,
            failed: testResults.failed,
            successRate: ((testResults.passed / testResults.total) * 100).toFixed(1),
            duration: duration.toFixed(2),
            timestamp: new Date().toISOString()
        },
        tests: testResults.results,
        errors: testResults.errors,
        recommendations: generateRecommendations()
    };
    
    // Save report
    const fs = require('fs');
    const reportPath = `/var/www/mrvl-backend/player_management_test_report_${Date.now()}.json`;
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`\n📄 Detailed report saved: ${reportPath}`);
    
    return report;
}

function generateRecommendations() {
    const recommendations = [];
    
    if (testResults.failed > 0) {
        recommendations.push('Review failed test cases and fix identified issues');
    }
    
    if (testResults.errors.some(e => e.includes('authentication'))) {
        recommendations.push('Check admin authentication system');
    }
    
    if (testResults.errors.some(e => e.includes('validation'))) {
        recommendations.push('Review field validation rules');
    }
    
    recommendations.push('Consider adding automated tests to CI/CD pipeline');
    recommendations.push('Monitor player management performance under load');
    
    return recommendations;
}

// Export for external use
if (require.main === module) {
    runAllTests().catch(console.error);
}

module.exports = {
    runAllTests,
    testResults,
    CONFIG
};