#!/usr/bin/env node

/**
 * COMPREHENSIVE TEAMS & PLAYERS CRUD VALIDATION TEST
 * 
 * This test validates all form fields for both teams and players to ensure
 * perfect field mapping between frontend and backend with no 400/500 errors.
 */

const fs = require('fs');

// Configuration
const API_BASE = 'https://mrvl.gg/api';
const ADMIN_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNjFlNjNkOTNkMDQ2MTJjN2VjNGVhNWMxNDQ1NTNmNjY5NzgzYmRlMTM2ZjlhOTU3ZjUwOWNhODJhNjhiMzk3N2NkOWQ3NzNiZGZhZTBkMGQiLCJpYXQiOjE3NTUwNzI1NDcuNzg5ODczLCJuYmYiOjE3NTUwNzI1NDcuNzg5ODc1LCJleHAiOjE3ODY2MDg1NDcuNzg3Njk2LCJzdWIiOiIxNjIiLCJzY29wZXMiOltdfQ.kjyFSr4VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7NKb-j6VAhZGEOCKJ4Dp6Hf6s_c-8V_DhHzHW9bKR4_xn_8K3j5HWfG7N';

// Helper function to make API requests
async function makeRequest(method, endpoint, data = null) {
    const url = `${API_BASE}${endpoint}`;
    const options = {
        method: method,
        headers: {
            'Authorization': `Bearer ${ADMIN_TOKEN}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const responseData = await response.text();
        
        let parsedData;
        try {
            parsedData = JSON.parse(responseData);
        } catch {
            parsedData = { raw_response: responseData };
        }

        return {
            status: response.status,
            ok: response.ok,
            data: parsedData,
            url: url
        };
    } catch (error) {
        return {
            status: 0,
            ok: false,
            data: { error: error.message },
            url: url
        };
    }
}

// Test data for comprehensive player validation
const COMPREHENSIVE_PLAYER_DATA = {
    // Basic Information
    username: 'TestPlayer' + Date.now(),
    realName: 'Test Real Name',
    name: 'Test Player Display Name',
    age: 25,
    country: 'US',
    nationality: 'American',
    region: 'NA',
    role: 'Duelist',
    status: 'active',
    
    // Gaming Information
    mainHero: 'Spider-Man',
    heroPool: 'Spider-Man, Iron Man, Captain America',
    altHeroes: ['Iron Man', 'Captain America'],
    teamId: null, // Will be set to test team
    jerseyNumber: 10,
    
    // Rating & Performance Stats
    rating: 2400,
    eloRating: 2450,
    peakElo: 2600,
    skillRating: 2300,
    wins: 150,
    losses: 30,
    kda: '2.85',
    totalMatches: 180,
    
    // Earnings Information
    totalEarnings: 25000.50,
    earnings: 15000.25,
    earningsAmount: 18000.75,
    earningsCurrency: 'USD',
    
    // Personal Information
    biography: 'Professional Marvel Rivals player with expertise in Duelist role. Known for exceptional mechanical skill and strategic gameplay.',
    birthDate: '1999-05-15',
    
    // Social Media Links
    socialMedia: {
        twitter: 'https://twitter.com/testplayer',
        instagram: 'https://instagram.com/testplayer',
        youtube: 'https://youtube.com/c/testplayer',
        twitch: 'https://twitch.tv/testplayer',
        discord: 'TestPlayer#1234',
        tiktok: 'https://tiktok.com/@testplayer',
        facebook: 'https://facebook.com/testplayer'
    }
};

// Test data for comprehensive team validation
const COMPREHENSIVE_TEAM_DATA = {
    // Basic Information
    name: 'Test Team ' + Date.now(),
    shortName: 'TEST',
    region: 'NA',
    country: 'United States',
    status: 'Active',
    
    // Performance & Rating
    rating: 2500,
    eloRating: 2550,
    peakElo: 2700,
    wins: 85,
    losses: 15,
    matchesPlayed: 100,
    winRate: 85.0,
    currentStreakCount: 5,
    currentStreakType: 'win',
    
    // Team Details
    founded: '2024',
    foundedDate: '2024-01-15',
    description: 'Professional Marvel Rivals team competing at the highest level with exceptional teamwork and strategic prowess.',
    achievements: 'Marvel Rivals Championship 2024 - 1st Place, Regional Tournament Winner',
    
    // Management
    manager: 'John Manager',
    owner: 'Team Organization LLC',
    captain: 'Team Captain',
    
    // Coach Information (Enhanced)
    coach: 'Master Coach',
    coachData: {
        name: 'Master Coach',
        realName: 'John Coach Smith',
        nationality: 'United States',
        experience: '5 years in Marvel Rivals coaching',
        achievements: 'Multiple championship wins, Coach of the Year 2024',
        avatar: ''
    },
    
    // Financial
    earnings: 150000.75,
    
    // Social Media Links
    socialLinks: {
        twitter: 'https://twitter.com/testteam',
        instagram: 'https://instagram.com/testteam',
        youtube: 'https://youtube.com/c/testteam',
        website: 'https://testteam.gg',
        discord: 'https://discord.gg/testteam',
        tiktok: 'https://tiktok.com/@testteam',
        facebook: 'https://facebook.com/testteam'
    }
};

// Test results tracking
let testResults = {
    timestamp: new Date().toISOString(),
    totalTests: 0,
    passedTests: 0,
    failedTests: 0,
    tests: [],
    issues: [],
    endpoints_tested: [],
    field_mapping_issues: [],
    validation_errors: []
};

function logTest(testName, status, details = {}) {
    testResults.totalTests++;
    const test = {
        name: testName,
        status: status,
        timestamp: new Date().toISOString(),
        ...details
    };
    
    testResults.tests.push(test);
    
    if (status === 'PASS') {
        testResults.passedTests++;
        console.log(`✅ ${testName}`);
    } else {
        testResults.failedTests++;
        console.log(`❌ ${testName}`);
        if (details.error) {
            console.log(`   Error: ${details.error}`);
        }
        if (details.response_data) {
            console.log(`   Response:`, JSON.stringify(details.response_data, null, 2));
        }
    }
}

function logIssue(type, description, endpoint = null, field = null) {
    const issue = {
        type: type,
        description: description,
        endpoint: endpoint,
        field: field,
        timestamp: new Date().toISOString()
    };
    
    testResults.issues.push(issue);
    console.log(`🔥 ISSUE [${type}]: ${description}`);
    if (endpoint) console.log(`   Endpoint: ${endpoint}`);
    if (field) console.log(`   Field: ${field}`);
}

async function testPlayerCRUD() {
    console.log('\n🧑‍💼 TESTING PLAYER CRUD OPERATIONS\n');
    
    let createdPlayerId = null;
    
    // Test 1: Create Player with ALL Fields
    console.log('📝 Testing Player Creation with All Fields...');
    const createResponse = await makeRequest('POST', '/admin/players', COMPREHENSIVE_PLAYER_DATA);
    
    testResults.endpoints_tested.push({
        method: 'POST',
        endpoint: '/admin/players',
        status: createResponse.status,
        tested_at: new Date().toISOString()
    });
    
    if (createResponse.status === 201 || createResponse.status === 200) {
        if (createResponse.data && (createResponse.data.id || createResponse.data.data?.id)) {
            createdPlayerId = createResponse.data.id || createResponse.data.data.id;
            logTest('Player Creation with All Fields', 'PASS', {
                player_id: createdPlayerId,
                response_status: createResponse.status
            });
        } else {
            logTest('Player Creation with All Fields', 'FAIL', {
                error: 'No player ID returned',
                response_status: createResponse.status,
                response_data: createResponse.data
            });
        }
    } else {
        logTest('Player Creation with All Fields', 'FAIL', {
            error: `HTTP ${createResponse.status}`,
            response_data: createResponse.data
        });
        
        if (createResponse.status === 404) {
            logIssue('ENDPOINT_MISSING', 'POST /admin/players endpoint not found', '/admin/players');
        } else if (createResponse.status === 422) {
            logIssue('VALIDATION_ERROR', 'Field validation failed during player creation', '/admin/players');
        } else if (createResponse.status === 500) {
            logIssue('SERVER_ERROR', 'Internal server error during player creation', '/admin/players');
        }
    }
    
    // Test 2: Read Player with All Fields
    if (createdPlayerId) {
        console.log('📖 Testing Player Retrieval...');
        const readResponse = await makeRequest('GET', `/admin/players/${createdPlayerId}`);
        
        testResults.endpoints_tested.push({
            method: 'GET',
            endpoint: `/admin/players/${createdPlayerId}`,
            status: readResponse.status,
            tested_at: new Date().toISOString()
        });
        
        if (readResponse.ok && readResponse.data) {
            logTest('Player Retrieval', 'PASS', {
                player_id: createdPlayerId,
                fields_returned: Object.keys(readResponse.data.data || readResponse.data || {}).length
            });
            
            // Validate field mapping
            const playerData = readResponse.data.data || readResponse.data;
            const expectedFields = [
                'username', 'realName', 'age', 'country', 'nationality', 'region', 'role', 'status',
                'mainHero', 'heroPool', 'teamId', 'eloRating', 'peakElo', 'skillRating', 
                'wins', 'losses', 'kda', 'totalEarnings', 'earnings', 'jerseyNumber', 
                'birthDate', 'biography', 'socialMedia'
            ];
            
            for (const field of expectedFields) {
                if (!(field in playerData) && !(field.toLowerCase() in playerData) && !(field.replace(/([A-Z])/g, '_$1').toLowerCase() in playerData)) {
                    testResults.field_mapping_issues.push({
                        entity: 'player',
                        field: field,
                        issue: 'Field missing in response',
                        endpoint: `/admin/players/${createdPlayerId}`
                    });
                }
            }
        } else {
            logTest('Player Retrieval', 'FAIL', {
                error: `HTTP ${readResponse.status}`,
                response_data: readResponse.data
            });
        }
    }
    
    // Test 3: Update Player with All Fields
    if (createdPlayerId) {
        console.log('✏️ Testing Player Update...');
        const updateData = {
            ...COMPREHENSIVE_PLAYER_DATA,
            username: COMPREHENSIVE_PLAYER_DATA.username + '_updated',
            age: 26,
            biography: 'Updated biography with new achievements and career highlights.'
        };
        
        const updateResponse = await makeRequest('PUT', `/admin/players/${createdPlayerId}`, updateData);
        
        testResults.endpoints_tested.push({
            method: 'PUT',
            endpoint: `/admin/players/${createdPlayerId}`,
            status: updateResponse.status,
            tested_at: new Date().toISOString()
        });
        
        if (updateResponse.ok) {
            logTest('Player Update with All Fields', 'PASS', {
                player_id: createdPlayerId,
                response_status: updateResponse.status
            });
        } else {
            logTest('Player Update with All Fields', 'FAIL', {
                error: `HTTP ${updateResponse.status}`,
                response_data: updateResponse.data
            });
            
            if (updateResponse.status === 422) {
                logIssue('VALIDATION_ERROR', 'Field validation failed during player update', `/admin/players/${createdPlayerId}`);
            }
        }
    }
    
    // Test 4: Test Field Validation
    console.log('🔍 Testing Player Field Validation...');
    const invalidPlayerData = {
        username: '', // Required field empty
        age: -5, // Invalid age
        role: 'InvalidRole', // Invalid role
        socialMedia: 'not_an_object' // Invalid social media format
    };
    
    const validationResponse = await makeRequest('POST', '/admin/players', invalidPlayerData);
    
    if (validationResponse.status === 422) {
        logTest('Player Field Validation', 'PASS', {
            validation_working: true,
            response_status: validationResponse.status
        });
    } else {
        logTest('Player Field Validation', 'FAIL', {
            error: 'Validation should have failed but did not',
            response_status: validationResponse.status,
            response_data: validationResponse.data
        });
        logIssue('VALIDATION_ERROR', 'Player validation rules not working properly', '/admin/players');
    }
    
    return createdPlayerId;
}

async function testTeamCRUD() {
    console.log('\n🏆 TESTING TEAM CRUD OPERATIONS\n');
    
    let createdTeamId = null;
    
    // Test 1: Create Team with ALL Fields
    console.log('📝 Testing Team Creation with All Fields...');
    const createResponse = await makeRequest('POST', '/admin/teams', COMPREHENSIVE_TEAM_DATA);
    
    testResults.endpoints_tested.push({
        method: 'POST',
        endpoint: '/admin/teams',
        status: createResponse.status,
        tested_at: new Date().toISOString()
    });
    
    if (createResponse.status === 201 || createResponse.status === 200) {
        if (createResponse.data && (createResponse.data.id || createResponse.data.data?.id)) {
            createdTeamId = createResponse.data.id || createResponse.data.data.id;
            logTest('Team Creation with All Fields', 'PASS', {
                team_id: createdTeamId,
                response_status: createResponse.status
            });
        } else {
            logTest('Team Creation with All Fields', 'FAIL', {
                error: 'No team ID returned',
                response_status: createResponse.status,
                response_data: createResponse.data
            });
        }
    } else {
        logTest('Team Creation with All Fields', 'FAIL', {
            error: `HTTP ${createResponse.status}`,
            response_data: createResponse.data
        });
        
        if (createResponse.status === 404) {
            logIssue('ENDPOINT_MISSING', 'POST /admin/teams endpoint not found', '/admin/teams');
        } else if (createResponse.status === 422) {
            logIssue('VALIDATION_ERROR', 'Field validation failed during team creation', '/admin/teams');
        } else if (createResponse.status === 500) {
            logIssue('SERVER_ERROR', 'Internal server error during team creation', '/admin/teams');
        }
    }
    
    // Test 2: Read Team with All Fields
    if (createdTeamId) {
        console.log('📖 Testing Team Retrieval...');
        const readResponse = await makeRequest('GET', `/admin/teams/${createdTeamId}`);
        
        testResults.endpoints_tested.push({
            method: 'GET',
            endpoint: `/admin/teams/${createdTeamId}`,
            status: readResponse.status,
            tested_at: new Date().toISOString()
        });
        
        if (readResponse.ok && readResponse.data) {
            logTest('Team Retrieval', 'PASS', {
                team_id: createdTeamId,
                fields_returned: Object.keys(readResponse.data.data || readResponse.data || {}).length
            });
            
            // Validate field mapping
            const teamData = readResponse.data.data || readResponse.data;
            const expectedFields = [
                'name', 'shortName', 'region', 'country', 'status', 'rating', 'eloRating', 
                'peakElo', 'wins', 'losses', 'matchesPlayed', 'winRate', 'founded', 
                'foundedDate', 'description', 'achievements', 'manager', 'owner', 
                'coach', 'coachData', 'earnings', 'socialLinks'
            ];
            
            for (const field of expectedFields) {
                if (!(field in teamData) && !(field.toLowerCase() in teamData) && !(field.replace(/([A-Z])/g, '_$1').toLowerCase() in teamData)) {
                    testResults.field_mapping_issues.push({
                        entity: 'team',
                        field: field,
                        issue: 'Field missing in response',
                        endpoint: `/admin/teams/${createdTeamId}`
                    });
                }
            }
        } else {
            logTest('Team Retrieval', 'FAIL', {
                error: `HTTP ${readResponse.status}`,
                response_data: readResponse.data
            });
        }
    }
    
    // Test 3: Update Team with All Fields
    if (createdTeamId) {
        console.log('✏️ Testing Team Update...');
        const updateData = {
            ...COMPREHENSIVE_TEAM_DATA,
            name: COMPREHENSIVE_TEAM_DATA.name + ' Updated',
            description: 'Updated team description with new achievements and roster changes.',
            earnings: 175000.50
        };
        
        const updateResponse = await makeRequest('PUT', `/admin/teams/${createdTeamId}`, updateData);
        
        testResults.endpoints_tested.push({
            method: 'PUT',
            endpoint: `/admin/teams/${createdTeamId}`,
            status: updateResponse.status,
            tested_at: new Date().toISOString()
        });
        
        if (updateResponse.ok) {
            logTest('Team Update with All Fields', 'PASS', {
                team_id: createdTeamId,
                response_status: updateResponse.status
            });
        } else {
            logTest('Team Update with All Fields', 'FAIL', {
                error: `HTTP ${updateResponse.status}`,
                response_data: updateResponse.data
            });
            
            if (updateResponse.status === 422) {
                logIssue('VALIDATION_ERROR', 'Field validation failed during team update', `/admin/teams/${createdTeamId}`);
            }
        }
    }
    
    // Test 4: Test Field Validation
    console.log('🔍 Testing Team Field Validation...');
    const invalidTeamData = {
        name: '', // Required field empty
        shortName: 'TOOLONGNAME', // Too long short name
        region: 'InvalidRegion', // Invalid region
        socialLinks: 'not_an_object' // Invalid social links format
    };
    
    const validationResponse = await makeRequest('POST', '/admin/teams', invalidTeamData);
    
    if (validationResponse.status === 422) {
        logTest('Team Field Validation', 'PASS', {
            validation_working: true,
            response_status: validationResponse.status
        });
    } else {
        logTest('Team Field Validation', 'FAIL', {
            error: 'Validation should have failed but did not',
            response_status: validationResponse.status,
            response_data: validationResponse.data
        });
        logIssue('VALIDATION_ERROR', 'Team validation rules not working properly', '/admin/teams');
    }
    
    return createdTeamId;
}

async function testSocialMediaURLFormatting() {
    console.log('\n🔗 TESTING SOCIAL MEDIA URL FORMATTING\n');
    
    const socialMediaTests = [
        { platform: 'twitter', url: 'https://twitter.com/testuser', expected: 'valid' },
        { platform: 'twitter', url: 'twitter.com/testuser', expected: 'should_be_fixed' },
        { platform: 'instagram', url: 'https://instagram.com/testuser', expected: 'valid' },
        { platform: 'youtube', url: 'https://youtube.com/c/testuser', expected: 'valid' },
        { platform: 'discord', url: 'TestUser#1234', expected: 'valid' },
        { platform: 'website', url: 'testteam.gg', expected: 'should_be_fixed' }
    ];
    
    for (const test of socialMediaTests) {
        const testData = {
            name: 'Social Media Test Team',
            shortName: 'SMT',
            region: 'NA',
            socialLinks: {
                [test.platform]: test.url
            }
        };
        
        const response = await makeRequest('POST', '/admin/teams', testData);
        
        if (response.ok) {
            logTest(`Social Media URL Formatting - ${test.platform}`, 'PASS', {
                platform: test.platform,
                input_url: test.url,
                response_status: response.status
            });
        } else {
            logTest(`Social Media URL Formatting - ${test.platform}`, 'FAIL', {
                platform: test.platform,
                input_url: test.url,
                error: `HTTP ${response.status}`,
                response_data: response.data
            });
        }
    }
}

async function testEndpointAvailability() {
    console.log('\n🔌 TESTING ENDPOINT AVAILABILITY\n');
    
    const requiredEndpoints = [
        { method: 'GET', path: '/admin/players', description: 'List Players' },
        { method: 'POST', path: '/admin/players', description: 'Create Player' },
        { method: 'GET', path: '/admin/players/1', description: 'Get Player' },
        { method: 'PUT', path: '/admin/players/1', description: 'Update Player' },
        { method: 'DELETE', path: '/admin/players/1', description: 'Delete Player' },
        { method: 'GET', path: '/admin/teams', description: 'List Teams' },
        { method: 'POST', path: '/admin/teams', description: 'Create Team' },
        { method: 'GET', path: '/admin/teams/1', description: 'Get Team' },
        { method: 'PUT', path: '/admin/teams/1', description: 'Update Team' },
        { method: 'DELETE', path: '/admin/teams/1', description: 'Delete Team' }
    ];
    
    for (const endpoint of requiredEndpoints) {
        const response = await makeRequest(endpoint.method, endpoint.path, 
            endpoint.method === 'POST' || endpoint.method === 'PUT' ? {} : null);
        
        testResults.endpoints_tested.push({
            method: endpoint.method,
            endpoint: endpoint.path,
            status: response.status,
            tested_at: new Date().toISOString()
        });
        
        if (response.status === 404) {
            logTest(`Endpoint Availability - ${endpoint.description}`, 'FAIL', {
                endpoint: endpoint.path,
                method: endpoint.method,
                error: 'Endpoint not found'
            });
            logIssue('ENDPOINT_MISSING', `${endpoint.method} ${endpoint.path} endpoint not implemented`, endpoint.path);
        } else if (response.status >= 200 && response.status < 300) {
            logTest(`Endpoint Availability - ${endpoint.description}`, 'PASS', {
                endpoint: endpoint.path,
                method: endpoint.method,
                response_status: response.status
            });
        } else if (response.status === 401 || response.status === 403) {
            logTest(`Endpoint Availability - ${endpoint.description}`, 'PASS', {
                endpoint: endpoint.path,
                method: endpoint.method,
                note: 'Endpoint exists but requires proper authentication'
            });
        } else {
            logTest(`Endpoint Availability - ${endpoint.description}`, 'PARTIAL', {
                endpoint: endpoint.path,
                method: endpoint.method,
                response_status: response.status,
                note: 'Endpoint exists but may have issues'
            });
        }
    }
}

async function runComprehensiveTest() {
    console.log('🚀 COMPREHENSIVE TEAMS & PLAYERS CRUD VALIDATION TEST');
    console.log('=====================================================');
    console.log(`🕐 Started at: ${new Date().toISOString()}`);
    console.log(`🌐 Testing API: ${API_BASE}`);
    console.log('');
    
    try {
        // Test endpoint availability first
        await testEndpointAvailability();
        
        // Test player CRUD operations
        const playerId = await testPlayerCRUD();
        
        // Test team CRUD operations
        const teamId = await testTeamCRUD();
        
        // Test social media URL formatting
        await testSocialMediaURLFormatting();
        
        // Generate summary report
        console.log('\n📊 TEST SUMMARY');
        console.log('================');
        console.log(`✅ Passed: ${testResults.passedTests}`);
        console.log(`❌ Failed: ${testResults.failedTests}`);
        console.log(`📈 Total: ${testResults.totalTests}`);
        console.log(`🎯 Success Rate: ${((testResults.passedTests / testResults.totalTests) * 100).toFixed(2)}%`);
        
        if (testResults.issues.length > 0) {
            console.log('\n🔥 CRITICAL ISSUES FOUND:');
            console.log('=========================');
            testResults.issues.forEach((issue, index) => {
                console.log(`${index + 1}. [${issue.type}] ${issue.description}`);
                if (issue.endpoint) console.log(`   Endpoint: ${issue.endpoint}`);
                if (issue.field) console.log(`   Field: ${issue.field}`);
            });
        }
        
        if (testResults.field_mapping_issues.length > 0) {
            console.log('\n🗺️ FIELD MAPPING ISSUES:');
            console.log('========================');
            testResults.field_mapping_issues.forEach((issue, index) => {
                console.log(`${index + 1}. [${issue.entity.toUpperCase()}] ${issue.field} - ${issue.issue}`);
                console.log(`   Endpoint: ${issue.endpoint}`);
            });
        }
        
        // Save detailed report
        const reportFilename = `team_player_crud_validation_report_${Date.now()}.json`;
        fs.writeFileSync(reportFilename, JSON.stringify(testResults, null, 2));
        console.log(`\n📋 Detailed report saved to: ${reportFilename}`);
        
    } catch (error) {
        console.error('\n💥 Test execution failed:', error);
        testResults.issues.push({
            type: 'TEST_EXECUTION_ERROR',
            description: error.message,
            timestamp: new Date().toISOString()
        });
    }
    
    console.log(`\n🏁 Test completed at: ${new Date().toISOString()}`);
}

// Run the comprehensive test
runComprehensiveTest().catch(console.error);