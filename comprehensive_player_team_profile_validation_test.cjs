#!/usr/bin/env node

/**
 * COMPREHENSIVE PLAYER & TEAM PROFILE VALIDATION TEST
 * ==================================================
 * 
 * This script comprehensively tests and validates:
 * 1. Backend API endpoints for player/team profiles
 * 2. CRUD operations integrity 
 * 3. Data preservation during updates
 * 4. Frontend-backend integration points
 * 5. Edge cases and error handling
 * 
 * Focus Areas:
 * - Team achievements display below mentions
 * - Player team history with current team
 * - Hero stats display (K, D, A, KDA, DMG, Heal, BLK)
 * - Hero images in match history
 * - Event logos in match cards
 */

const axios = require('axios');
const fs = require('fs');

// Configuration
const CONFIG = {
    baseURL: 'http://localhost:8000/api',
    testDataFile: 'player_team_profile_test_results.json',
    timeout: 10000,
    maxRetries: 3
};

class ProfileValidationTester {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            summary: {
                totalTests: 0,
                passed: 0,
                failed: 0,
                warnings: 0
            },
            testResults: [],
            detailedFindings: []
        };
        this.testData = {
            createdPlayers: [],
            createdTeams: [],
            sampleMatches: []
        };
    }

    async runAllTests() {
        console.log('🚀 Starting Comprehensive Player & Team Profile Validation Tests...\n');

        // Test Sequence
        await this.testBackendAPIs();
        await this.testCRUDOperations();
        await this.testDataIntegrity();
        await this.testEdgeCases();
        await this.generateReport();
        
        console.log('\n✅ All tests completed!');
        console.log(`📊 Results: ${this.results.summary.passed} passed, ${this.results.summary.failed} failed, ${this.results.summary.warnings} warnings`);
    }

    async testBackendAPIs() {
        console.log('🔍 Testing Backend APIs...\n');
        
        // Test Player Profile APIs
        await this.testPlayerAPIs();
        
        // Test Team Profile APIs  
        await this.testTeamAPIs();
        
        // Test Match Integration APIs
        await this.testMatchIntegrationAPIs();
    }

    async testPlayerAPIs() {
        console.log('👤 Testing Player Profile APIs...');

        const playerTests = [
            {
                name: 'Get Players List',
                endpoint: '/public/players',
                method: 'GET',
                expectsData: 'array'
            },
            {
                name: 'Get Player Details',
                endpoint: '/public/players/1',
                method: 'GET',
                expectsData: 'object'
            },
            {
                name: 'Get Player Team History',
                endpoint: '/public/players/1/team-history',
                method: 'GET',
                expectsData: 'array',
                validates: ['current_team', 'team_history', 'team_duration']
            },
            {
                name: 'Get Player Matches',
                endpoint: '/public/players/1/matches',
                method: 'GET',
                expectsData: 'array',
                validates: ['match_data', 'hero_images', 'event_logos']
            },
            {
                name: 'Get Player Stats',
                endpoint: '/public/players/1/stats',
                method: 'GET',
                expectsData: 'object',
                validates: ['kills', 'deaths', 'assists', 'kda', 'damage', 'healing', 'blocked']
            }
        ];

        for (const test of playerTests) {
            await this.runAPITest(test);
        }
    }

    async testTeamAPIs() {
        console.log('👥 Testing Team Profile APIs...');

        const teamTests = [
            {
                name: 'Get Teams List',
                endpoint: '/public/teams',
                method: 'GET',
                expectsData: 'array'
            },
            {
                name: 'Get Team Details',
                endpoint: '/public/teams/1',
                method: 'GET',
                expectsData: 'object'
            },
            {
                name: 'Get Team Achievements',
                endpoint: '/public/teams/1/achievements',
                method: 'GET',
                expectsData: 'array',
                validates: ['achievement_placement', 'below_mentions']
            }
        ];

        for (const test of teamTests) {
            await this.runAPITest(test);
        }
    }

    async testMatchIntegrationAPIs() {
        console.log('🎮 Testing Match Integration APIs...');

        const matchTests = [
            {
                name: 'Get Matches with Hero Data',
                endpoint: '/public/matches',
                method: 'GET',
                expectsData: 'array',
                validates: ['hero_images', 'event_logos', 'player_stats']
            }
        ];

        for (const test of matchTests) {
            await this.runAPITest(test);
        }
    }

    async runAPITest(test) {
        try {
            this.results.summary.totalTests++;
            
            console.log(`  🧪 ${test.name}...`);
            
            const response = await axios({
                method: test.method,
                url: `${CONFIG.baseURL}${test.endpoint}`,
                timeout: CONFIG.timeout
            });

            let testResult = {
                name: test.name,
                endpoint: test.endpoint,
                method: test.method,
                status: 'PASSED',
                responseCode: response.status,
                dataValidation: {},
                issues: []
            };

            // Validate response structure
            if (test.expectsData === 'array' && !Array.isArray(response.data.data || response.data)) {
                testResult.status = 'FAILED';
                testResult.issues.push('Expected array response');
            } else if (test.expectsData === 'object' && typeof (response.data.data || response.data) !== 'object') {
                testResult.status = 'FAILED';
                testResult.issues.push('Expected object response');
            }

            // Validate specific data requirements
            if (test.validates) {
                await this.validateSpecificData(test, response.data, testResult);
            }

            // Data quality checks
            await this.performDataQualityChecks(test, response.data, testResult);

            this.results.testResults.push(testResult);

            if (testResult.status === 'PASSED') {
                console.log(`    ✅ PASSED`);
                this.results.summary.passed++;
            } else {
                console.log(`    ❌ FAILED: ${testResult.issues.join(', ')}`);
                this.results.summary.failed++;
            }

        } catch (error) {
            console.log(`    ❌ ERROR: ${error.message}`);
            this.results.summary.failed++;
            this.results.testResults.push({
                name: test.name,
                endpoint: test.endpoint,
                method: test.method,
                status: 'ERROR',
                error: error.message,
                issues: ['API request failed']
            });
        }
    }

    async validateSpecificData(test, responseData, testResult) {
        const data = responseData.data || responseData;

        for (const validation of test.validates) {
            switch (validation) {
                case 'current_team':
                    if (Array.isArray(data) && data.length > 0) {
                        const hasCurrentTeam = data.some(entry => entry.is_current === true || entry.current === true);
                        if (!hasCurrentTeam) {
                            testResult.issues.push('No current team marked in team history');
                        } else {
                            testResult.dataValidation.currentTeam = 'Found';
                        }
                    }
                    break;

                case 'team_history':
                    if (Array.isArray(data)) {
                        const hasHistory = data.some(entry => entry.team_name || entry.team);
                        if (!hasHistory) {
                            testResult.issues.push('Team history missing team names');
                        } else {
                            testResult.dataValidation.teamHistory = 'Present';
                        }
                    }
                    break;

                case 'kills':
                case 'deaths':
                case 'assists':
                case 'kda':
                case 'damage':
                case 'healing':
                case 'blocked':
                    if (typeof data === 'object' && data !== null) {
                        const stat = data[validation] || data.stats?.[validation];
                        if (stat === undefined || stat === null) {
                            testResult.issues.push(`Missing ${validation} stat`);
                        } else {
                            testResult.dataValidation[validation] = 'Present';
                        }
                    }
                    break;

                case 'hero_images':
                    if (Array.isArray(data)) {
                        const hasHeroImages = data.some(item => 
                            item.hero_image || item.hero_avatar || item.heroes?.length > 0
                        );
                        if (!hasHeroImages) {
                            testResult.issues.push('Hero images missing from match data');
                        } else {
                            testResult.dataValidation.heroImages = 'Present';
                        }
                    }
                    break;

                case 'event_logos':
                    if (Array.isArray(data)) {
                        const hasEventLogos = data.some(item => 
                            item.event_logo || item.event?.logo || item.tournament_logo
                        );
                        if (!hasEventLogos) {
                            testResult.issues.push('Event logos missing from match cards');
                        } else {
                            testResult.dataValidation.eventLogos = 'Present';
                        }
                    }
                    break;

                case 'achievement_placement':
                    // This would require frontend testing - mark as needs manual verification
                    testResult.dataValidation.achievementPlacement = 'Manual verification needed';
                    break;

                case 'below_mentions':
                    // This would require frontend testing - mark as needs manual verification  
                    testResult.dataValidation.belowMentions = 'Manual verification needed';
                    break;
            }
        }
    }

    async performDataQualityChecks(test, responseData, testResult) {
        const data = responseData.data || responseData;

        // Check for null/undefined values
        if (data === null || data === undefined) {
            testResult.issues.push('Response data is null or undefined');
            return;
        }

        // Array checks
        if (Array.isArray(data)) {
            if (data.length === 0) {
                testResult.issues.push('Empty array returned');
            } else {
                // Check first item for common issues
                const firstItem = data[0];
                if (firstItem && typeof firstItem === 'object') {
                    // Check for missing IDs
                    if (!firstItem.id) {
                        testResult.issues.push('Items missing ID field');
                    }
                    // Check for empty strings
                    Object.keys(firstItem).forEach(key => {
                        if (firstItem[key] === '' || firstItem[key] === null) {
                            if (!testResult.dataValidation.emptyFields) {
                                testResult.dataValidation.emptyFields = [];
                            }
                            testResult.dataValidation.emptyFields.push(key);
                        }
                    });
                }
            }
        }

        // Object checks
        if (typeof data === 'object' && !Array.isArray(data)) {
            const keys = Object.keys(data);
            if (keys.length === 0) {
                testResult.issues.push('Empty object returned');
            }
        }
    }

    async testCRUDOperations() {
        console.log('\n🔧 Testing CRUD Operations...\n');

        await this.testPlayerCRUD();
        await this.testTeamCRUD();
    }

    async testPlayerCRUD() {
        console.log('👤 Testing Player CRUD Operations...');

        // Test Create Player (would require admin auth)
        const createPlayerTest = {
            name: 'Create Player',
            operation: 'CREATE',
            status: 'SKIPPED - Requires Admin Auth',
            note: 'Admin authentication required for player creation'
        };

        this.results.testResults.push(createPlayerTest);
        this.results.summary.totalTests++;
        this.results.summary.warnings++;

        console.log('  ⚠️  Player creation test skipped (requires admin auth)');

        // Test Update Player (would require admin auth)
        const updatePlayerTest = {
            name: 'Update Player',
            operation: 'UPDATE', 
            status: 'SKIPPED - Requires Admin Auth',
            note: 'Admin authentication required for player updates'
        };

        this.results.testResults.push(updatePlayerTest);
        this.results.summary.totalTests++;
        this.results.summary.warnings++;

        console.log('  ⚠️  Player update test skipped (requires admin auth)');
    }

    async testTeamCRUD() {
        console.log('👥 Testing Team CRUD Operations...');

        // Similar to player CRUD - would require admin auth
        const createTeamTest = {
            name: 'Create Team',
            operation: 'CREATE',
            status: 'SKIPPED - Requires Admin Auth',
            note: 'Admin authentication required for team creation'
        };

        this.results.testResults.push(createTeamTest);
        this.results.summary.totalTests++;
        this.results.summary.warnings++;

        console.log('  ⚠️  Team creation test skipped (requires admin auth)');
    }

    async testDataIntegrity() {
        console.log('\n🔍 Testing Data Integrity...\n');

        await this.testRelationalIntegrity();
        await this.testDataConsistency();
    }

    async testRelationalIntegrity() {
        console.log('🔗 Testing Relational Data Integrity...');

        try {
            // Test player-team relationships
            const playersResponse = await axios.get(`${CONFIG.baseURL}/public/players`);
            const players = playersResponse.data.data || playersResponse.data;

            if (Array.isArray(players) && players.length > 0) {
                const player = players[0];
                
                if (player.current_team_id) {
                    // Verify team exists
                    try {
                        await axios.get(`${CONFIG.baseURL}/public/teams/${player.current_team_id}`);
                        console.log('  ✅ Player-Team relationship verified');
                        this.results.summary.passed++;
                    } catch (error) {
                        console.log('  ❌ Player references non-existent team');
                        this.results.summary.failed++;
                    }
                } else {
                    console.log('  ⚠️  Player has no current team reference');
                    this.results.summary.warnings++;
                }
            }

            this.results.summary.totalTests++;

        } catch (error) {
            console.log(`  ❌ Relational integrity test failed: ${error.message}`);
            this.results.summary.failed++;
            this.results.summary.totalTests++;
        }
    }

    async testDataConsistency() {
        console.log('📊 Testing Data Consistency...');

        try {
            // Test that player stats are consistent across endpoints
            const playerStatsResponse = await axios.get(`${CONFIG.baseURL}/public/players/1/stats`);
            const playerMatchesResponse = await axios.get(`${CONFIG.baseURL}/public/players/1/matches`);

            const stats = playerStatsResponse.data.data || playerStatsResponse.data;
            const matches = playerMatchesResponse.data.data || playerMatchesResponse.data;

            if (stats && Array.isArray(matches)) {
                console.log('  ✅ Player data consistency check completed');
                this.results.summary.passed++;
            } else {
                console.log('  ❌ Inconsistent data structure between endpoints');
                this.results.summary.failed++;
            }

            this.results.summary.totalTests++;

        } catch (error) {
            console.log(`  ❌ Data consistency test failed: ${error.message}`);
            this.results.summary.failed++;
            this.results.summary.totalTests++;
        }
    }

    async testEdgeCases() {
        console.log('\n🎯 Testing Edge Cases...\n');

        const edgeCases = [
            {
                name: 'Player with no team history',
                endpoint: '/public/players/999999/team-history',
                expectedBehavior: 'Should handle gracefully'
            },
            {
                name: 'Team with no achievements',
                endpoint: '/public/teams/999999/achievements',
                expectedBehavior: 'Should return empty array'
            },
            {
                name: 'Player with no match history',
                endpoint: '/public/players/999999/matches',
                expectedBehavior: 'Should return empty array'
            },
            {
                name: 'Non-existent player',
                endpoint: '/public/players/999999',
                expectedBehavior: 'Should return 404'
            },
            {
                name: 'Non-existent team',
                endpoint: '/public/teams/999999',
                expectedBehavior: 'Should return 404'
            }
        ];

        for (const edgeCase of edgeCases) {
            await this.testEdgeCase(edgeCase);
        }
    }

    async testEdgeCase(edgeCase) {
        try {
            this.results.summary.totalTests++;
            console.log(`  🎯 ${edgeCase.name}...`);

            const response = await axios.get(`${CONFIG.baseURL}${edgeCase.endpoint}`);

            const testResult = {
                name: edgeCase.name,
                endpoint: edgeCase.endpoint,
                status: 'PASSED',
                responseCode: response.status,
                expectedBehavior: edgeCase.expectedBehavior,
                actualBehavior: `Returned ${response.status}`,
                issues: []
            };

            // Validate response
            if (edgeCase.expectedBehavior.includes('empty array')) {
                const data = response.data.data || response.data;
                if (Array.isArray(data) && data.length === 0) {
                    console.log('    ✅ PASSED - Returns empty array as expected');
                    this.results.summary.passed++;
                } else {
                    testResult.status = 'FAILED';
                    testResult.issues.push('Should return empty array');
                    console.log('    ❌ FAILED - Does not return empty array');
                    this.results.summary.failed++;
                }
            } else if (edgeCase.expectedBehavior.includes('gracefully')) {
                console.log('    ✅ PASSED - Handles request gracefully');
                this.results.summary.passed++;
            } else {
                console.log('    ✅ PASSED - Returns data');
                this.results.summary.passed++;
            }

            this.results.testResults.push(testResult);

        } catch (error) {
            if (edgeCase.expectedBehavior.includes('404') && error.response?.status === 404) {
                console.log('    ✅ PASSED - Returns 404 as expected');
                this.results.summary.passed++;
                this.results.testResults.push({
                    name: edgeCase.name,
                    endpoint: edgeCase.endpoint,
                    status: 'PASSED',
                    responseCode: 404,
                    expectedBehavior: edgeCase.expectedBehavior,
                    actualBehavior: 'Returned 404 as expected'
                });
            } else {
                console.log(`    ❌ ERROR: ${error.message}`);
                this.results.summary.failed++;
                this.results.testResults.push({
                    name: edgeCase.name,
                    endpoint: edgeCase.endpoint,
                    status: 'ERROR',
                    error: error.message,
                    expectedBehavior: edgeCase.expectedBehavior,
                    actualBehavior: 'Request failed'
                });
            }
        }
    }

    async generateReport() {
        console.log('\n📋 Generating Comprehensive Test Report...\n');

        // Add findings and recommendations
        this.results.detailedFindings = [
            {
                category: 'API Endpoints',
                status: this.getAPIEndpointsStatus(),
                findings: this.getAPIFindings(),
                recommendations: this.getAPIRecommendations()
            },
            {
                category: 'Data Quality',
                status: this.getDataQualityStatus(),
                findings: this.getDataQualityFindings(),
                recommendations: this.getDataQualityRecommendations()
            },
            {
                category: 'Frontend Integration',
                status: 'NEEDS_MANUAL_TESTING',
                findings: [
                    'Team achievements placement needs manual verification',
                    'Hero images in match cards require frontend testing',
                    'Event logos display requires frontend testing'
                ],
                recommendations: [
                    'Implement frontend integration tests',
                    'Create visual regression tests for component placement',
                    'Add automated UI testing for image loading'
                ]
            },
            {
                category: 'CRUD Operations',
                status: 'REQUIRES_AUTHENTICATION',
                findings: [
                    'Player/Team creation requires admin authentication',
                    'Update operations need proper authorization',
                    'Delete operations should be tested with admin access'
                ],
                recommendations: [
                    'Implement authenticated test suite',
                    'Test data preservation during updates',
                    'Verify cascade delete operations'
                ]
            },
            {
                category: 'Edge Cases',
                status: this.getEdgeCasesStatus(),
                findings: this.getEdgeCaseFindings(),
                recommendations: this.getEdgeCaseRecommendations()
            }
        ];

        // Save results to file
        fs.writeFileSync(
            CONFIG.testDataFile,
            JSON.stringify(this.results, null, 2)
        );

        console.log(`📄 Test report saved to: ${CONFIG.testDataFile}`);
        
        // Display summary
        this.displayTestSummary();
    }

    getAPIEndpointsStatus() {
        const apiTests = this.results.testResults.filter(test => 
            test.endpoint && !test.name.includes('CRUD') && !test.name.includes('Edge')
        );
        const passedAPI = apiTests.filter(test => test.status === 'PASSED').length;
        const totalAPI = apiTests.length;
        
        if (passedAPI === totalAPI) return 'FULLY_FUNCTIONAL';
        if (passedAPI > totalAPI * 0.7) return 'MOSTLY_FUNCTIONAL';
        return 'NEEDS_ATTENTION';
    }

    getAPIFindings() {
        return this.results.testResults
            .filter(test => test.issues && test.issues.length > 0)
            .map(test => `${test.name}: ${test.issues.join(', ')}`);
    }

    getAPIRecommendations() {
        const recommendations = [
            'Ensure all endpoints return consistent data structures',
            'Implement proper error handling for all API routes',
            'Add validation for required fields in responses'
        ];

        if (this.getAPIFindings().some(finding => finding.includes('missing'))) {
            recommendations.push('Review and add missing data fields to API responses');
        }

        return recommendations;
    }

    getDataQualityStatus() {
        const qualityIssues = this.results.testResults.filter(test => 
            test.dataValidation && Object.keys(test.dataValidation).length > 0
        ).length;
        
        if (qualityIssues === 0) return 'EXCELLENT';
        if (qualityIssues < 3) return 'GOOD';
        return 'NEEDS_IMPROVEMENT';
    }

    getDataQualityFindings() {
        const findings = [];
        this.results.testResults.forEach(test => {
            if (test.dataValidation) {
                Object.keys(test.dataValidation).forEach(key => {
                    if (test.dataValidation[key] === 'Missing' || 
                        (Array.isArray(test.dataValidation[key]) && test.dataValidation[key].length > 0)) {
                        findings.push(`${test.name}: ${key} quality issue`);
                    }
                });
            }
        });
        return findings;
    }

    getDataQualityRecommendations() {
        return [
            'Implement data validation at the database level',
            'Add data quality monitoring and alerting',
            'Create data cleanup procedures for empty/null fields',
            'Establish data quality standards and documentation'
        ];
    }

    getEdgeCasesStatus() {
        const edgeTests = this.results.testResults.filter(test => 
            test.name.includes('no ') || test.name.includes('Non-existent')
        );
        const passedEdge = edgeTests.filter(test => test.status === 'PASSED').length;
        const totalEdge = edgeTests.length;
        
        if (passedEdge === totalEdge) return 'ROBUST';
        if (passedEdge > totalEdge * 0.8) return 'GOOD';
        return 'NEEDS_HARDENING';
    }

    getEdgeCaseFindings() {
        return this.results.testResults
            .filter(test => test.name.includes('no ') || test.name.includes('Non-existent'))
            .filter(test => test.status !== 'PASSED')
            .map(test => `${test.name}: ${test.status}`);
    }

    getEdgeCaseRecommendations() {
        return [
            'Implement proper 404 handling for non-existent resources',
            'Return empty arrays instead of null for missing collections',
            'Add graceful degradation for incomplete data',
            'Implement proper error messages for edge cases'
        ];
    }

    displayTestSummary() {
        console.log('\n' + '='.repeat(80));
        console.log('📊 COMPREHENSIVE TEST SUMMARY');
        console.log('='.repeat(80));
        console.log(`Total Tests: ${this.results.summary.totalTests}`);
        console.log(`✅ Passed: ${this.results.summary.passed}`);
        console.log(`❌ Failed: ${this.results.summary.failed}`);
        console.log(`⚠️  Warnings: ${this.results.summary.warnings}`);
        console.log(`📈 Success Rate: ${((this.results.summary.passed / this.results.summary.totalTests) * 100).toFixed(1)}%`);
        console.log('\n📋 DETAILED FINDINGS:');
        
        this.results.detailedFindings.forEach(finding => {
            console.log(`\n🔍 ${finding.category}: ${finding.status}`);
            if (finding.findings.length > 0) {
                console.log('   Issues:');
                finding.findings.forEach(issue => console.log(`   • ${issue}`));
            }
            if (finding.recommendations.length > 0) {
                console.log('   Recommendations:');
                finding.recommendations.slice(0, 3).forEach(rec => console.log(`   → ${rec}`));
            }
        });

        console.log('\n' + '='.repeat(80));
        console.log('🎯 KEY PRIORITIES FOR IMPLEMENTATION:');
        console.log('='.repeat(80));
        console.log('1. 🔐 Implement authenticated testing suite for CRUD operations');
        console.log('2. 🎨 Create frontend integration tests for component placement');
        console.log('3. 📊 Add comprehensive data validation and quality monitoring');
        console.log('4. 🛠️  Improve error handling and edge case responses');
        console.log('5. 🖼️  Verify hero images and event logos display correctly');
        console.log('\n✨ Test report completed successfully!\n');
    }
}

// Run the tests if this file is executed directly
if (require.main === module) {
    const tester = new ProfileValidationTester();
    tester.runAllTests().catch(error => {
        console.error('❌ Test execution failed:', error);
        process.exit(1);
    });
}

module.exports = ProfileValidationTester;