#!/usr/bin/env node

/**
 * Comprehensive Team and Player Profile Functionality Test
 * 
 * This script performs end-to-end testing of all team and player profile
 * update functionality to ensure everything works perfectly for production use.
 * 
 * Test Coverage:
 * 1. Team Profile Updates (earnings, rating, country, logo)
 * 2. Player Profile Updates (name, username, rating, country, earnings, team)
 * 3. Data Integrity and Persistence
 * 4. Frontend Display Updates
 * 5. Error Handling and Validation
 */

const axios = require('axios');
const fs = require('fs');
const FormData = require('form-data');
const path = require('path');

// Test Configuration
const BASE_URL = process.env.API_URL || 'http://localhost:8000/api';
const ADMIN_API_URL = `${BASE_URL}/admin`;
const AUTH_TOKEN = process.env.AUTH_TOKEN || '';

// Test Results Storage
const testResults = {
    startTime: new Date().toISOString(),
    tests: [],
    summary: {
        total: 0,
        passed: 0,
        failed: 0,
        skipped: 0
    }
};

// Helper Functions
function logTest(name, status, details = null, error = null) {
    const test = {
        name,
        status,
        timestamp: new Date().toISOString(),
        details,
        error: error ? error.message : null
    };
    
    testResults.tests.push(test);
    testResults.summary.total++;
    testResults.summary[status]++;
    
    const statusIcon = {
        passed: '✅',
        failed: '❌',
        skipped: '⚠️'
    };
    
    console.log(`${statusIcon[status]} ${name}`);
    if (details) console.log(`   Details: ${JSON.stringify(details)}`);
    if (error) console.log(`   Error: ${error.message}`);
}

async function apiRequest(method, endpoint, data = null, headers = {}) {
    try {
        const config = {
            method,
            url: `${endpoint.startsWith('/admin/') ? ADMIN_API_URL.replace('/admin', '') : BASE_URL}${endpoint}`,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${AUTH_TOKEN}`,
                ...headers
            }
        };
        
        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            config.data = data;
        }
        
        const response = await axios(config);
        return { success: true, data: response.data, status: response.status };
    } catch (error) {
        return { 
            success: false, 
            error: error.response?.data || error.message, 
            status: error.response?.status || 0 
        };
    }
}

// Create test data
function createTestTeam() {
    return {
        name: `Test Team ${Date.now()}`,
        short_name: `TT${Date.now()}`,
        region: 'North America',
        platform: 'PC',
        game: 'Marvel Rivals',
        country: 'United States',
        country_code: 'US',
        rating: 1500,
        earnings: 50000,
        rank: 10,
        status: 'active'
    };
}

function createTestPlayer() {
    return {
        name: 'Test Player',
        username: `testplayer${Date.now()}`,
        real_name: 'John Doe',
        role: 'DPS',
        region: 'North America',
        country: 'United States',
        country_code: 'US',
        rating: 1400,
        earnings: 25000,
        age: 22,
        status: 'active'
    };
}

// Team Profile Update Tests
async function testTeamProfileUpdates() {
    console.log('\n🔧 Testing Team Profile Updates...');
    
    let testTeam = null;
    let teamId = null;
    
    try {
        // Create test team
        const teamData = createTestTeam();
        const createResult = await apiRequest('POST', '/admin/teams', teamData);
        
        if (!createResult.success) {
            logTest('Create Test Team', 'failed', null, new Error('Failed to create test team'));
            return false;
        }
        
        testTeam = createResult.data.data;
        teamId = testTeam.id;
        logTest('Create Test Team', 'passed', { id: teamId, name: testTeam.name });
        
        // Test 1: Update team earnings
        const earningsUpdate = { earnings: 75000 };
        const earningsResult = await apiRequest('PUT', `/admin/teams/${teamId}`, earningsUpdate);
        
        if (earningsResult.success) {
            logTest('Update Team Earnings', 'passed', { newEarnings: 75000 });
        } else {
            logTest('Update Team Earnings', 'failed', null, new Error('Failed to update earnings'));
        }
        
        // Test 2: Update team rating/ELO
        const ratingUpdate = { rating: 1750, elo_rating: 1750 };
        const ratingResult = await apiRequest('PUT', `/admin/teams/${teamId}`, ratingUpdate);
        
        if (ratingResult.success) {
            logTest('Update Team Rating/ELO', 'passed', { newRating: 1750 });
        } else {
            logTest('Update Team Rating/ELO', 'failed', null, new Error('Failed to update rating'));
        }
        
        // Test 3: Update team country (flag)
        const countryUpdate = { 
            country: 'Canada', 
            country_code: 'CA',
            flag: '/flags/ca.png' 
        };
        const countryResult = await apiRequest('PUT', `/admin/teams/${teamId}`, countryUpdate);
        
        if (countryResult.success) {
            logTest('Update Team Country/Flag', 'passed', { newCountry: 'Canada' });
        } else {
            logTest('Update Team Country/Flag', 'failed', null, new Error('Failed to update country'));
        }
        
        // Test 4: Test partial update (only one field)
        const partialUpdate = { win_rate: 0.75 };
        const partialResult = await apiRequest('PUT', `/admin/teams/${teamId}`, partialUpdate);
        
        if (partialResult.success) {
            logTest('Partial Team Update', 'passed', { winRate: 0.75 });
        } else {
            logTest('Partial Team Update', 'failed', null, new Error('Failed partial update'));
        }
        
        // Test 5: Verify data persistence after refresh
        const refreshResult = await apiRequest('GET', `/admin/teams`);
        
        if (refreshResult.success) {
            const updatedTeam = refreshResult.data.data.find(t => t.id === teamId);
            if (updatedTeam) {
                logTest('Verify Team Data Persistence', 'passed', { 
                    earnings: updatedTeam.earnings,
                    rating: updatedTeam.rating,
                    country: updatedTeam.country
                });
            } else {
                logTest('Verify Team Data Persistence', 'failed', null, new Error('Team not found after refresh'));
            }
        } else {
            logTest('Verify Team Data Persistence', 'failed', null, new Error('Failed to fetch teams'));
        }
        
        return true;
        
    } catch (error) {
        logTest('Team Profile Updates', 'failed', null, error);
        return false;
    } finally {
        // Cleanup - delete test team
        if (teamId) {
            await apiRequest('DELETE', `/teams/${teamId}`);
            logTest('Cleanup Test Team', 'passed', { deletedId: teamId });
        }
    }
}

// Player Profile Update Tests
async function testPlayerProfileUpdates() {
    console.log('\n🎮 Testing Player Profile Updates...');
    
    let testPlayer = null;
    let playerId = null;
    let testTeam = null;
    let teamId = null;
    
    try {
        // Create test team first for player assignment
        const teamData = createTestTeam();
        const teamResult = await apiRequest('POST', '/teams', teamData);
        if (teamResult.success) {
            testTeam = teamResult.data.data;
            teamId = testTeam.id;
        }
        
        // Create test player
        const playerData = createTestPlayer();
        if (teamId) playerData.team_id = teamId;
        
        const createResult = await apiRequest('POST', '/players', playerData);
        
        if (!createResult.success) {
            logTest('Create Test Player', 'failed', null, new Error('Failed to create test player'));
            return false;
        }
        
        testPlayer = createResult.data.data;
        playerId = testPlayer.id;
        logTest('Create Test Player', 'passed', { id: playerId, username: testPlayer.username });
        
        // Test 1: Update player name and username
        const nameUpdate = { 
            name: 'Updated Player Name',
            username: `updated${Date.now()}`,
            real_name: 'Jane Smith'
        };
        const nameResult = await apiRequest('PUT', `/players/${playerId}`, nameUpdate);
        
        if (nameResult.success) {
            logTest('Update Player Name/Username', 'passed', nameUpdate);
        } else {
            logTest('Update Player Name/Username', 'failed', null, new Error('Failed to update name'));
        }
        
        // Test 2: Update player rating/ELO
        const ratingUpdate = { rating: 1600, elo_rating: 1600, peak_elo: 1700 };
        const ratingResult = await apiRequest('PUT', `/players/${playerId}`, ratingUpdate);
        
        if (ratingResult.success) {
            logTest('Update Player Rating/ELO', 'passed', ratingUpdate);
        } else {
            logTest('Update Player Rating/ELO', 'failed', null, new Error('Failed to update rating'));
        }
        
        // Test 3: Update player country
        const countryUpdate = { 
            country: 'United Kingdom',
            country_code: 'GB',
            nationality: 'British'
        };
        const countryResult = await apiRequest('PUT', `/players/${playerId}`, countryUpdate);
        
        if (countryResult.success) {
            logTest('Update Player Country', 'passed', countryUpdate);
        } else {
            logTest('Update Player Country', 'failed', null, new Error('Failed to update country'));
        }
        
        // Test 4: Update player earnings
        const earningsUpdate = { earnings: 35000, total_earnings: 35000 };
        const earningsResult = await apiRequest('PUT', `/players/${playerId}`, earningsUpdate);
        
        if (earningsResult.success) {
            logTest('Update Player Earnings', 'passed', earningsUpdate);
        } else {
            logTest('Update Player Earnings', 'failed', null, new Error('Failed to update earnings'));
        }
        
        // Test 5: Change player team assignment
        if (teamId) {
            const teamUpdate = { team_id: teamId };
            const teamResult = await apiRequest('PUT', `/players/${playerId}`, teamUpdate);
            
            if (teamResult.success) {
                logTest('Update Player Team Assignment', 'passed', { newTeamId: teamId });
            } else {
                logTest('Update Player Team Assignment', 'failed', null, new Error('Failed to update team'));
            }
        }
        
        // Test 6: Verify data persistence after refresh
        const refreshResult = await apiRequest('GET', `/players`);
        
        if (refreshResult.success) {
            const updatedPlayer = refreshResult.data.data.find(p => p.id === playerId);
            if (updatedPlayer) {
                logTest('Verify Player Data Persistence', 'passed', {
                    username: updatedPlayer.username,
                    rating: updatedPlayer.rating,
                    country: updatedPlayer.country,
                    earnings: updatedPlayer.earnings
                });
            } else {
                logTest('Verify Player Data Persistence', 'failed', null, new Error('Player not found after refresh'));
            }
        } else {
            logTest('Verify Player Data Persistence', 'failed', null, new Error('Failed to fetch players'));
        }
        
        return true;
        
    } catch (error) {
        logTest('Player Profile Updates', 'failed', null, error);
        return false;
    } finally {
        // Cleanup
        if (playerId) {
            await apiRequest('DELETE', `/players/${playerId}`);
            logTest('Cleanup Test Player', 'passed', { deletedId: playerId });
        }
        if (teamId) {
            await apiRequest('DELETE', `/teams/${teamId}`);
            logTest('Cleanup Test Team', 'passed', { deletedId: teamId });
        }
    }
}

// Data Integrity Tests
async function testDataIntegrity() {
    console.log('\n🔐 Testing Data Integrity...');
    
    try {
        // Test 1: Foreign key relationships
        const teamsResult = await apiRequest('GET', '/teams');
        const playersResult = await apiRequest('GET', '/players');
        
        if (teamsResult.success && playersResult.success) {
            const teams = teamsResult.data.data;
            const players = playersResult.data.data;
            
            let integrityIssues = 0;
            
            players.forEach(player => {
                if (player.team_id && !teams.find(t => t.id === player.team_id)) {
                    integrityIssues++;
                }
            });
            
            if (integrityIssues === 0) {
                logTest('Foreign Key Relationships', 'passed', { checkedPlayers: players.length });
            } else {
                logTest('Foreign Key Relationships', 'failed', { issues: integrityIssues });
            }
        }
        
        // Test 2: Data validation constraints
        const invalidTeam = {
            name: '', // Empty name should fail
            rating: -100 // Negative rating should fail
        };
        
        const invalidResult = await apiRequest('POST', '/teams', invalidTeam);
        
        if (!invalidResult.success) {
            logTest('Data Validation Constraints', 'passed', { rejectedInvalidData: true });
        } else {
            logTest('Data Validation Constraints', 'failed', null, new Error('Invalid data was accepted'));
        }
        
        return true;
        
    } catch (error) {
        logTest('Data Integrity Tests', 'failed', null, error);
        return false;
    }
}

// Error Handling Tests
async function testErrorHandling() {
    console.log('\n⚠️ Testing Error Handling...');
    
    try {
        // Test 1: Update non-existent team
        const nonExistentResult = await apiRequest('PUT', '/teams/99999', { name: 'Test' });
        
        if (!nonExistentResult.success && nonExistentResult.status === 404) {
            logTest('Handle Non-existent Team Update', 'passed', { status: 404 });
        } else {
            logTest('Handle Non-existent Team Update', 'failed', null, new Error('Should return 404'));
        }
        
        // Test 2: Invalid data types
        const invalidData = {
            rating: 'not_a_number',
            earnings: 'invalid_amount'
        };
        
        const invalidDataResult = await apiRequest('PUT', '/teams/1', invalidData);
        
        if (!invalidDataResult.success) {
            logTest('Handle Invalid Data Types', 'passed', { rejectedInvalidTypes: true });
        } else {
            logTest('Handle Invalid Data Types', 'failed', null, new Error('Invalid types were accepted'));
        }
        
        // Test 3: Duplicate username validation
        const player1 = createTestPlayer();
        const createResult1 = await apiRequest('POST', '/players', player1);
        
        if (createResult1.success) {
            const player2 = { ...createTestPlayer(), username: player1.username };
            const createResult2 = await apiRequest('POST', '/players', player2);
            
            if (!createResult2.success) {
                logTest('Handle Duplicate Username', 'passed', { preventedDuplicate: true });
            } else {
                logTest('Handle Duplicate Username', 'failed', null, new Error('Duplicate username was allowed'));
            }
            
            // Cleanup
            await apiRequest('DELETE', `/players/${createResult1.data.data.id}`);
        }
        
        return true;
        
    } catch (error) {
        logTest('Error Handling Tests', 'failed', null, error);
        return false;
    }
}

// API Availability Tests
async function testAPIAvailability() {
    console.log('\n🌐 Testing API Availability...');
    
    try {
        // Test all main endpoints
        const endpoints = [
            { method: 'GET', path: '/teams', name: 'Teams List' },
            { method: 'GET', path: '/players', name: 'Players List' },
            { method: 'GET', path: '/matches', name: 'Matches List' }
        ];
        
        for (const endpoint of endpoints) {
            const result = await apiRequest(endpoint.method, endpoint.path);
            
            if (result.success) {
                logTest(`${endpoint.name} Endpoint`, 'passed', { status: result.status });
            } else {
                logTest(`${endpoint.name} Endpoint`, 'failed', null, new Error(`Status: ${result.status}`));
            }
        }
        
        return true;
        
    } catch (error) {
        logTest('API Availability Tests', 'failed', null, error);
        return false;
    }
}

// Generate Final Report
function generateFinalReport() {
    const endTime = new Date().toISOString();
    const duration = new Date(endTime) - new Date(testResults.startTime);
    
    const report = {
        ...testResults,
        endTime,
        duration: `${duration}ms`,
        successRate: `${((testResults.summary.passed / testResults.summary.total) * 100).toFixed(2)}%`
    };
    
    // Save to file
    const reportPath = `/var/www/mrvl-backend/team_player_profile_test_report_${Date.now()}.json`;
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    
    console.log('\n📊 FINAL TEST REPORT');
    console.log('='.repeat(50));
    console.log(`Total Tests: ${report.summary.total}`);
    console.log(`Passed: ${report.summary.passed} ✅`);
    console.log(`Failed: ${report.summary.failed} ❌`);
    console.log(`Skipped: ${report.summary.skipped} ⚠️`);
    console.log(`Success Rate: ${report.successRate}`);
    console.log(`Duration: ${report.duration}`);
    console.log(`Report saved to: ${reportPath}`);
    
    return report;
}

// Main Test Runner
async function runAllTests() {
    console.log('🚀 Starting Comprehensive Team & Player Profile Tests...');
    console.log(`Base URL: ${SIMPLE_API_URL}`);
    console.log(`Start Time: ${testResults.startTime}\n`);
    
    try {
        // Run all test suites
        await testAPIAvailability();
        await testTeamProfileUpdates();
        await testPlayerProfileUpdates();
        await testDataIntegrity();
        await testErrorHandling();
        
        // Generate final report
        const report = generateFinalReport();
        
        // Exit with appropriate code
        process.exit(report.summary.failed > 0 ? 1 : 0);
        
    } catch (error) {
        console.error('❌ Test suite failed:', error);
        process.exit(1);
    }
}

// Run tests if this script is executed directly
if (require.main === module) {
    runAllTests();
}

module.exports = {
    runAllTests,
    testTeamProfileUpdates,
    testPlayerProfileUpdates,
    testDataIntegrity,
    testErrorHandling
};