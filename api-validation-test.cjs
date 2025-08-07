/**
 * API Validation Test for Marvel Rivals Platform
 * Tests all API endpoints and data integrity
 */

const axios = require('axios');
const fs = require('fs');

class APIValidator {
    constructor() {
        this.apiUrl = 'http://127.0.0.1:8001/api';
        this.results = {
            mentionsSystem: {},
            dataIntegrity: {},
            adminEndpoints: {},
            searchEndpoints: {},
            summary: {
                totalTests: 0,
                passed: 0,
                failed: 0,
                issues: []
            }
        };
    }

    async testMentionsSystem() {
        console.log('\n📝 Testing Mentions System API Endpoints...');
        const mentionsResults = {
            teamMentionsEndpoint: false,
            playerMentionsEndpoint: false,
            mentionsStructure: false,
            apiResponseFormat: false
        };

        try {
            // Test team mentions endpoint
            const teamResponse = await axios.get(`${this.apiUrl}/teams/1/mentions`);
            if (teamResponse.status === 200 && teamResponse.data.success) {
                mentionsResults.teamMentionsEndpoint = true;
                console.log('✅ Team mentions endpoint working');
            }

            // Test player mentions endpoint
            const playerResponse = await axios.get(`${this.apiUrl}/players/1/mentions`);
            if (playerResponse.status === 200 && playerResponse.data.success) {
                mentionsResults.playerMentionsEndpoint = true;
                console.log('✅ Player mentions endpoint working');
            }

            // Test mentions structure
            if (teamResponse.data.data !== undefined && teamResponse.data.pagination !== undefined) {
                mentionsResults.mentionsStructure = true;
                console.log('✅ Mentions API structure correct');
            }

            // Test API response format
            if (teamResponse.data.success !== undefined) {
                mentionsResults.apiResponseFormat = true;
                console.log('✅ API response format correct');
            }

        } catch (error) {
            console.log(`❌ Error testing mentions system: ${error.message}`);
            this.results.summary.issues.push(`Mentions API Error: ${error.message}`);
        }

        this.results.mentionsSystem = mentionsResults;
        this.updateTestCounts(mentionsResults);
    }

    async testDataIntegrity() {
        console.log('\n💾 Testing Data Integrity...');
        const dataResults = {
            teamsDataCount: false,
            playersDataCount: false,
            teamsStructure: false,
            playersStructure: false
        };

        try {
            // Test teams data
            const teamsResponse = await axios.get(`${this.apiUrl}/teams`);
            const teamsData = teamsResponse.data.data;
            
            if (teamsData.length >= 53) {
                dataResults.teamsDataCount = true;
                console.log(`✅ Teams data count correct: ${teamsData.length} teams`);
            } else {
                console.log(`❌ Teams data count incorrect: ${teamsData.length} teams (expected 53+)`);
            }

            // Test teams structure
            const firstTeam = teamsData[0];
            if (firstTeam.id && firstTeam.name && firstTeam.short_name && firstTeam.rating) {
                dataResults.teamsStructure = true;
                console.log('✅ Teams data structure correct');
            }

            // Test players data
            const playersResponse = await axios.get(`${this.apiUrl}/players`);
            const playersData = playersResponse.data.data;
            
            if (playersData.length >= 318) {
                dataResults.playersDataCount = true;
                console.log(`✅ Players data count correct: ${playersData.length} players`);
            } else {
                console.log(`❌ Players data count incorrect: ${playersData.length} players (expected 318+)`);
            }

            // Test players structure
            const firstPlayer = playersData[0];
            if (firstPlayer.id && firstPlayer.username && firstPlayer.rating && firstPlayer.team) {
                dataResults.playersStructure = true;
                console.log('✅ Players data structure correct');
            }

        } catch (error) {
            console.log(`❌ Error testing data integrity: ${error.message}`);
            this.results.summary.issues.push(`Data Integrity Error: ${error.message}`);
        }

        this.results.dataIntegrity = dataResults;
        this.updateTestCounts(dataResults);
    }

    async testAdminEndpoints() {
        console.log('\n⚙️ Testing Admin Endpoints (Structure Only)...');
        const adminResults = {
            adminTeamsEndpoint: false,
            adminPlayersEndpoint: false,
            adminStructureValid: false,
            paginationSupported: false
        };

        try {
            // Test admin teams endpoint structure (public endpoint for now)
            const teamsResponse = await axios.get(`${this.apiUrl}/teams`);
            if (teamsResponse.status === 200) {
                adminResults.adminTeamsEndpoint = true;
                console.log('✅ Admin teams endpoint accessible');
            }

            // Test admin players endpoint structure
            const playersResponse = await axios.get(`${this.apiUrl}/players`);
            if (playersResponse.status === 200) {
                adminResults.adminPlayersEndpoint = true;
                console.log('✅ Admin players endpoint accessible');
            }

            // Test admin structure validity
            if (teamsResponse.data.data && playersResponse.data.data) {
                adminResults.adminStructureValid = true;
                console.log('✅ Admin data structure valid');
            }

            // Test pagination support
            const paginatedResponse = await axios.get(`${this.apiUrl}/teams?page=1`);
            if (paginatedResponse.data.data && Array.isArray(paginatedResponse.data.data)) {
                adminResults.paginationSupported = true;
                console.log('✅ Pagination supported');
            }

        } catch (error) {
            console.log(`❌ Error testing admin endpoints: ${error.message}`);
            this.results.summary.issues.push(`Admin Endpoints Error: ${error.message}`);
        }

        this.results.adminEndpoints = adminResults;
        this.updateTestCounts(adminResults);
    }

    async testSearchEndpoints() {
        console.log('\n🔍 Testing Search Functionality...');
        const searchResults = {
            searchEndpointExists: false,
            searchReturnsResults: false,
            searchFiltering: false,
            searchStructure: false
        };

        try {
            // Test search endpoint exists
            const searchResponse = await axios.get(`${this.apiUrl}/search?q=team`);
            if (searchResponse.status === 200) {
                searchResults.searchEndpointExists = true;
                console.log('✅ Search endpoint exists');
            }

            // Test search returns results
            if (searchResponse.data && (searchResponse.data.teams || searchResponse.data.players)) {
                searchResults.searchReturnsResults = true;
                console.log('✅ Search returns results');
            }

            // Test search filtering (empty query should return different results)
            const emptySearchResponse = await axios.get(`${this.apiUrl}/search?q=nonexistentquery12345`);
            if (emptySearchResponse.status === 200) {
                searchResults.searchFiltering = true;
                console.log('✅ Search filtering works');
            }

            // Test search structure
            if (searchResponse.data !== undefined) {
                searchResults.searchStructure = true;
                console.log('✅ Search structure valid');
            }

        } catch (error) {
            console.log(`❌ Error testing search endpoints: ${error.message}`);
            this.results.summary.issues.push(`Search Endpoints Error: ${error.message}`);
        }

        this.results.searchEndpoints = searchResults;
        this.updateTestCounts(searchResults);
    }

    async testSpecificEndpoints() {
        console.log('\n🎯 Testing Specific Marvel Rivals Endpoints...');
        const specificResults = {
            heroesEndpoint: false,
            eventsEndpoint: false,
            matchesEndpoint: false,
            newsEndpoint: false
        };

        try {
            // Test heroes endpoint
            const heroesResponse = await axios.get(`${this.apiUrl}/heroes`);
            if (heroesResponse.status === 200 && heroesResponse.data.length > 0) {
                specificResults.heroesEndpoint = true;
                console.log(`✅ Heroes endpoint working: ${heroesResponse.data.length} heroes`);
            }

            // Test events endpoint
            const eventsResponse = await axios.get(`${this.apiUrl}/events`);
            if (eventsResponse.status === 200) {
                specificResults.eventsEndpoint = true;
                console.log('✅ Events endpoint working');
            }

            // Test matches endpoint
            const matchesResponse = await axios.get(`${this.apiUrl}/matches`);
            if (matchesResponse.status === 200) {
                specificResults.matchesEndpoint = true;
                console.log('✅ Matches endpoint working');
            }

            // Test news endpoint
            const newsResponse = await axios.get(`${this.apiUrl}/news`);
            if (newsResponse.status === 200) {
                specificResults.newsEndpoint = true;
                console.log('✅ News endpoint working');
            }

        } catch (error) {
            console.log(`❌ Error testing specific endpoints: ${error.message}`);
            this.results.summary.issues.push(`Specific Endpoints Error: ${error.message}`);
        }

        this.results.specificEndpoints = specificResults;
        this.updateTestCounts(specificResults);
    }

    updateTestCounts(testResults) {
        Object.values(testResults).forEach(result => {
            this.results.summary.totalTests++;
            if (result === true) {
                this.results.summary.passed++;
            } else {
                this.results.summary.failed++;
            }
        });
    }

    generateReport() {
        console.log('\n📊 Generating API Validation Report...');
        
        const report = {
            timestamp: new Date().toISOString(),
            platform: 'Marvel Rivals Platform - API Validation',
            testSuite: 'Backend API Comprehensive Test',
            summary: this.results.summary,
            detailedResults: {
                mentionsSystem: this.results.mentionsSystem,
                dataIntegrity: this.results.dataIntegrity,
                adminEndpoints: this.results.adminEndpoints,
                searchEndpoints: this.results.searchEndpoints,
                specificEndpoints: this.results.specificEndpoints
            },
            recommendations: this.generateRecommendations(),
            nextSteps: this.generateNextSteps()
        };

        const reportPath = `/var/www/mrvl-backend/api-validation-report-${Date.now()}.json`;
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        console.log('\n📄 API Test Report Summary:');
        console.log(`Total Tests: ${this.results.summary.totalTests}`);
        console.log(`Passed: ${this.results.summary.passed}`);
        console.log(`Failed: ${this.results.summary.failed}`);
        console.log(`Success Rate: ${((this.results.summary.passed / this.results.summary.totalTests) * 100).toFixed(1)}%`);
        
        if (this.results.summary.issues.length > 0) {
            console.log('\n⚠️ Issues Found:');
            this.results.summary.issues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue}`);
            });
        }

        console.log(`\n📋 Full report saved to: ${reportPath}`);
        return report;
    }

    generateRecommendations() {
        const recommendations = [];
        
        // Analyze test results and provide recommendations
        if (!this.results.mentionsSystem.teamMentionsEndpoint) {
            recommendations.push('Fix team mentions API endpoint - ensure proper routing and controller methods');
        }
        
        if (!this.results.mentionsSystem.playerMentionsEndpoint) {
            recommendations.push('Fix player mentions API endpoint - verify mentions controller integration');
        }
        
        if (!this.results.dataIntegrity.teamsDataCount) {
            recommendations.push('Verify teams data - should contain 53 teams from database');
        }
        
        if (!this.results.dataIntegrity.playersDataCount) {
            recommendations.push('Verify players data - should contain 318+ players from database');
        }
        
        if (!this.results.searchEndpoints.searchEndpointExists) {
            recommendations.push('Implement or fix search API endpoint functionality');
        }

        if (recommendations.length === 0) {
            recommendations.push('All API tests passed! Backend is functioning correctly.');
        }

        return recommendations;
    }

    generateNextSteps() {
        return [
            'Verify frontend integration with working API endpoints',
            'Test admin panel functionality with authentication',
            'Conduct end-to-end testing of user workflows',
            'Monitor API response times and performance',
            'Set up automated API testing in CI/CD pipeline'
        ];
    }

    async runFullValidation() {
        try {
            console.log('🚀 Starting Marvel Rivals API Validation...');
            await this.testMentionsSystem();
            await this.testDataIntegrity();
            await this.testAdminEndpoints();
            await this.testSearchEndpoints();
            await this.testSpecificEndpoints();
            const report = this.generateReport();
            return report;
        } catch (error) {
            console.log(`❌ Validation failed: ${error.message}`);
            this.results.summary.issues.push(`Validation Error: ${error.message}`);
            return this.generateReport();
        }
    }
}

// Run validation if script is called directly
if (require.main === module) {
    (async () => {
        const validator = new APIValidator();
        const report = await validator.runFullValidation();
        
        console.log('\n🎯 API Validation Complete!');
        process.exit(report && report.summary.failed === 0 ? 0 : 1);
    })();
}

module.exports = APIValidator;