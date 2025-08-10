#!/usr/bin/env node

/**
 * FOCUSED FRONTEND TESTING FOR CRITICAL BUGS
 * 
 * Testing specific functionality areas:
 * 1. Events System CRUD
 * 2. Bracket System functionality 
 * 3. Rankings System
 * 4. Admin operations
 * 5. Image/Logo functionality
 */

const http = require('http');
const https = require('https');
const fs = require('fs');
const { URL } = require('url');

class FocusedFrontendTester {
    constructor() {
        this.baseUrl = 'http://127.0.0.1:8000';
        this.testResults = [];
        this.authToken = null;
        this.criticalIssues = [];
        this.highPriorityIssues = [];
        this.mediumPriorityIssues = [];
    }

    async runFocusedTests() {
        console.log('🔍 FOCUSED FRONTEND TESTING SUITE');
        console.log('=' .repeat(50));
        
        try {
            // 1. Basic API connectivity
            await this.testBasicConnectivity();
            
            // 2. Authentication test
            await this.testAuthentication();
            
            // 3. Events System testing
            await this.testEventsSystemFunctionality();
            
            // 4. Brackets testing
            await this.testBracketSystemFunctionality();
            
            // 5. Rankings testing
            await this.testRankingsSystemFunctionality();
            
            // 6. Image/Logo testing
            await this.testImageFunctionality();
            
            // 7. Generate report
            this.generateReport();
            
        } catch (error) {
            console.error('❌ Test execution failed:', error);
        }
    }

    async testBasicConnectivity() {
        console.log('\n📡 Testing Basic API Connectivity...');
        
        try {
            const response = await this.makeRequest('/api/events');
            if (response && response.data) {
                this.logSuccess('✅ API connectivity working');
                this.logSuccess(`✅ Found ${response.data.length} events in system`);
                
                // Check event structure
                if (response.data.length > 0) {
                    const event = response.data[0];
                    const requiredFields = ['id', 'name', 'description', 'schedule'];
                    for (const field of requiredFields) {
                        if (!event[field]) {
                            this.logError(`Event missing required field: ${field}`, 'HIGH');
                        }
                    }
                }
            } else {
                this.logError('Events API response invalid format', 'CRITICAL');
            }
        } catch (error) {
            this.logError(`API connectivity failed: ${error.message}`, 'CRITICAL');
        }
    }

    async testAuthentication() {
        console.log('\n🔐 Testing Authentication...');
        
        try {
            const loginResponse = await this.makeRequest('/api/auth/login', 'POST', {
                email: 'admin@example.com',
                password: 'password'
            });
            
            if (loginResponse && loginResponse.access_token) {
                this.authToken = loginResponse.access_token;
                this.logSuccess('✅ Admin authentication successful');
                
                // Test authenticated endpoint
                const userResponse = await this.makeRequest('/api/auth/user');
                if (userResponse && userResponse.role === 'admin') {
                    this.logSuccess('✅ Admin role verified');
                } else {
                    this.logError('Admin role verification failed', 'HIGH');
                }
            } else {
                this.logError('Authentication failed - no access token', 'CRITICAL');
            }
        } catch (error) {
            this.logError(`Authentication error: ${error.message}`, 'CRITICAL');
        }
    }

    async testEventsSystemFunctionality() {
        console.log('\n🎯 Testing Events System...');
        
        try {
            // 1. Test events listing
            const events = await this.makeRequest('/api/events');
            this.logSuccess(`✅ Events listing works - ${events.data ? events.data.length : 0} events`);
            
            // 2. Test individual event detail
            if (events.data && events.data.length > 0) {
                const firstEvent = events.data[0];
                const eventDetail = await this.makeRequest(`/api/events/${firstEvent.id}`);
                
                if (eventDetail && eventDetail.data) {
                    this.logSuccess('✅ Event detail page works');
                    
                    // Check logo functionality
                    if (eventDetail.data.logo && eventDetail.data.logo.url) {
                        try {
                            await this.makeRequest(eventDetail.data.logo.url, 'GET', null, false);
                            this.logSuccess('✅ Event logo accessible');
                        } catch (logoError) {
                            this.logError(`Event logo broken: ${eventDetail.data.logo.url}`, 'MEDIUM');
                        }
                    }
                } else {
                    this.logError('Event detail page not working', 'HIGH');
                }
            }
            
            // 3. Test admin event operations (if authenticated)
            if (this.authToken) {
                await this.testAdminEventOperations();
            }
            
        } catch (error) {
            this.logError(`Events system error: ${error.message}`, 'HIGH');
        }
    }

    async testAdminEventOperations() {
        console.log('  📝 Testing Admin Event Operations...');
        
        try {
            // Create test event
            const testEvent = {
                name: 'Test Event CRUD',
                description: 'Testing CRUD operations',
                type: 'tournament',
                start_date: '2025-12-01',
                end_date: '2025-12-03',
                max_teams: 8,
                prize_pool: 10000
            };
            
            const createdEvent = await this.makeRequest('/api/admin/events', 'POST', testEvent);
            
            if (createdEvent && createdEvent.data && createdEvent.data.id) {
                this.logSuccess('✅ Event creation works');
                const eventId = createdEvent.data.id;
                
                // Test event update
                const updateData = {
                    name: 'Updated Test Event',
                    prize_pool: 20000
                };
                
                const updatedEvent = await this.makeRequest(`/api/admin/events/${eventId}`, 'PUT', updateData);
                
                if (updatedEvent && updatedEvent.data && updatedEvent.data.name === updateData.name) {
                    this.logSuccess('✅ Event update works');
                } else {
                    this.logError('Event update failed', 'HIGH');
                }
                
                // Test event deletion
                await this.makeRequest(`/api/admin/events/${eventId}`, 'DELETE');
                
                // Verify deletion
                try {
                    await this.makeRequest(`/api/events/${eventId}`);
                    this.logError('Event not properly deleted', 'HIGH');
                } catch (deleteVerifyError) {
                    if (deleteVerifyError.message.includes('404')) {
                        this.logSuccess('✅ Event deletion works');
                    } else {
                        throw deleteVerifyError;
                    }
                }
                
            } else {
                this.logError('Event creation failed', 'HIGH');
            }
            
        } catch (error) {
            this.logError(`Admin event operations error: ${error.message}`, 'HIGH');
        }
    }

    async testBracketSystemFunctionality() {
        console.log('\n🏆 Testing Bracket System...');
        
        try {
            // Check if brackets endpoint exists
            const brackets = await this.makeRequest('/api/brackets');
            this.logSuccess(`✅ Brackets API accessible - ${brackets.length || 0} brackets found`);
            
            // Test bracket detail if any exist
            if (Array.isArray(brackets) && brackets.length > 0) {
                const bracketDetail = await this.makeRequest(`/api/brackets/${brackets[0].id}`);
                if (bracketDetail) {
                    this.logSuccess('✅ Bracket detail page works');
                    
                    // Check bracket structure
                    if (bracketDetail.matches && Array.isArray(bracketDetail.matches)) {
                        this.logSuccess('✅ Bracket matches data available');
                    } else {
                        this.logError('Bracket matches data missing or invalid', 'HIGH');
                    }
                    
                    if (bracketDetail.teams && Array.isArray(bracketDetail.teams)) {
                        this.logSuccess('✅ Bracket teams data available');
                    } else {
                        this.logError('Bracket teams data missing or invalid', 'HIGH');
                    }
                }
            }
            
            // Test admin bracket operations
            if (this.authToken) {
                await this.testAdminBracketOperations();
            }
            
        } catch (error) {
            this.logError(`Bracket system error: ${error.message}`, 'HIGH');
        }
    }

    async testAdminBracketOperations() {
        console.log('  🔧 Testing Admin Bracket Operations...');
        
        try {
            // Get events to test bracket generation
            const events = await this.makeRequest('/api/admin/events');
            
            if (events && events.data && events.data.length > 0) {
                const testEvent = events.data[0];
                
                // Test bracket generation
                try {
                    const bracketResponse = await this.makeRequest(`/api/admin/events/${testEvent.id}/generate-bracket`, 'POST', {});
                    
                    if (bracketResponse && bracketResponse.bracket_id) {
                        this.logSuccess('✅ Bracket generation works');
                    } else {
                        this.logError('Bracket generation returned invalid response', 'HIGH');
                    }
                } catch (bracketGenError) {
                    if (bracketGenError.message.includes('enough teams')) {
                        this.logInfo('ℹ️ Bracket generation needs more teams (expected)');
                    } else {
                        this.logError(`Bracket generation error: ${bracketGenError.message}`, 'HIGH');
                    }
                }
            }
        } catch (error) {
            this.logError(`Admin bracket operations error: ${error.message}`, 'HIGH');
        }
    }

    async testRankingsSystemFunctionality() {
        console.log('\n🏅 Testing Rankings System...');
        
        try {
            // Test team rankings
            const teamRankingsResponse = await this.makeRequest('/api/rankings/teams');
            const teamRankings = teamRankingsResponse.data || teamRankingsResponse;
            
            if (Array.isArray(teamRankings)) {
                this.logSuccess(`✅ Team rankings works - ${teamRankings.length} teams`);
                
                if (teamRankings.length > 0) {
                    const firstTeam = teamRankings[0];
                    const requiredFields = ['id', 'name', 'rank']; // Changed from 'ranking' to 'rank'
                    for (const field of requiredFields) {
                        if (firstTeam[field] === undefined) {
                            this.logError(`Team ranking missing field: ${field}`, 'MEDIUM');
                        }
                    }
                }
            } else {
                this.logError('Team rankings endpoint returns invalid format', 'HIGH');
            }
            
            // Test player rankings
            const playerRankingsResponse = await this.makeRequest('/api/rankings/players');
            const playerRankings = playerRankingsResponse.data || playerRankingsResponse;
            
            if (Array.isArray(playerRankings)) {
                this.logSuccess(`✅ Player rankings works - ${playerRankings.length} players`);
                
                if (playerRankings.length > 0) {
                    const firstPlayer = playerRankings[0];
                    const requiredFields = ['id', 'username', 'ranking']; // Updated to match actual structure
                    for (const field of requiredFields) {
                        if (firstPlayer[field] === undefined) {
                            this.logError(`Player ranking missing field: ${field}`, 'MEDIUM');
                        }
                    }
                }
            } else {
                this.logError('Player rankings endpoint returns invalid format', 'HIGH');
            }
            
            // Test search functionality
            await this.testRankingSearch();
            
            // Test filtering
            await this.testRankingFilters();
            
        } catch (error) {
            this.logError(`Rankings system error: ${error.message}`, 'HIGH');
        }
    }

    async testRankingSearch() {
        try {
            // Test team search
            const searchResponse = await this.makeRequest('/api/rankings/teams?search=test');
            const searchResults = searchResponse.data || searchResponse;
            if (Array.isArray(searchResults)) {
                this.logSuccess('✅ Team ranking search works');
            } else {
                this.logError('Team ranking search returns invalid format', 'MEDIUM');
            }
        } catch (error) {
            this.logError(`Ranking search error: ${error.message}`, 'MEDIUM');
        }
    }

    async testRankingFilters() {
        try {
            // Test region filter
            const regionResponse = await this.makeRequest('/api/rankings/teams?region=NA');
            const regionResults = regionResponse.data || regionResponse;
            if (Array.isArray(regionResults)) {
                this.logSuccess('✅ Team ranking region filter works');
            } else {
                this.logError('Team ranking region filter returns invalid format', 'MEDIUM');
            }
        } catch (error) {
            this.logError(`Ranking filter error: ${error.message}`, 'MEDIUM');
        }
    }

    async testImageFunctionality() {
        console.log('\n🖼️ Testing Image/Logo Functionality...');
        
        try {
            // Test fallback images
            const fallbackImages = [
                '/images/team-placeholder.svg',
                '/images/player-placeholder.svg',
                '/images/news-placeholder.svg',
                '/images/default-placeholder.svg'
            ];
            
            for (const imagePath of fallbackImages) {
                try {
                    await this.makeRequest(imagePath, 'GET', null, false);
                    this.logSuccess(`✅ Fallback image works: ${imagePath}`);
                } catch (imageError) {
                    this.logError(`Fallback image broken: ${imagePath}`, 'HIGH');
                }
            }
            
            // Test team logos from API
            const teams = await this.makeRequest('/api/teams');
            if (teams && teams.data) {
                const teamsWithLogos = teams.data.filter(team => team.logo && team.logo.url);
                
                if (teamsWithLogos.length > 0) {
                    for (const team of teamsWithLogos.slice(0, 3)) {
                        try {
                            await this.makeRequest(team.logo.url, 'GET', null, false);
                            this.logSuccess(`✅ Team logo works: ${team.name}`);
                        } catch (logoError) {
                            this.logError(`Team logo broken: ${team.name} - ${team.logo.url}`, 'MEDIUM');
                        }
                    }
                } else {
                    this.logInfo('ℹ️ No teams with logos found for testing');
                }
            }
            
        } catch (error) {
            this.logError(`Image functionality error: ${error.message}`, 'HIGH');
        }
    }

    async makeRequest(endpoint, method = 'GET', data = null, parseJson = true) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.baseUrl}${endpoint}`;
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };
        
        if (this.authToken) {
            options.headers['Authorization'] = `Bearer ${this.authToken}`;
        }
        
        return new Promise((resolve, reject) => {
            const parsedUrl = new URL(url);
            const lib = parsedUrl.protocol === 'https:' ? https : http;
            
            const req = lib.request(parsedUrl, options, (res) => {
                let responseData = '';
                
                res.on('data', (chunk) => {
                    responseData += chunk;
                });
                
                res.on('end', () => {
                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        if (parseJson) {
                            try {
                                resolve(JSON.parse(responseData));
                            } catch (parseError) {
                                resolve(responseData);
                            }
                        } else {
                            resolve(responseData);
                        }
                    } else {
                        reject(new Error(`HTTP ${res.statusCode}: ${responseData}`));
                    }
                });
            });
            
            req.on('error', (error) => {
                reject(error);
            });
            
            if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
                req.write(JSON.stringify(data));
            }
            
            req.end();
        });
    }

    logSuccess(message) {
        console.log(message);
        this.testResults.push({ type: 'SUCCESS', message, timestamp: new Date().toISOString() });
    }

    logError(message, severity) {
        console.log(`❌ [${severity}] ${message}`);
        const errorRecord = { type: 'ERROR', message, severity, timestamp: new Date().toISOString() };
        this.testResults.push(errorRecord);
        
        switch (severity) {
            case 'CRITICAL':
                this.criticalIssues.push(errorRecord);
                break;
            case 'HIGH':
                this.highPriorityIssues.push(errorRecord);
                break;
            case 'MEDIUM':
                this.mediumPriorityIssues.push(errorRecord);
                break;
        }
    }

    logInfo(message) {
        console.log(message);
        this.testResults.push({ type: 'INFO', message, timestamp: new Date().toISOString() });
    }

    generateReport() {
        const successCount = this.testResults.filter(r => r.type === 'SUCCESS').length;
        const errorCount = this.testResults.filter(r => r.type === 'ERROR').length;
        
        console.log('\n' + '='.repeat(60));
        console.log('📋 FOCUSED FRONTEND TEST RESULTS');
        console.log('='.repeat(60));
        console.log(`✅ Successful Tests: ${successCount}`);
        console.log(`❌ Failed Tests: ${errorCount}`);
        console.log(`🔴 Critical Issues: ${this.criticalIssues.length}`);
        console.log(`🟠 High Priority Issues: ${this.highPriorityIssues.length}`);
        console.log(`🟡 Medium Priority Issues: ${this.mediumPriorityIssues.length}`);
        
        if (this.criticalIssues.length > 0) {
            console.log('\n🚨 CRITICAL ISSUES:');
            this.criticalIssues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue.message}`);
            });
        }
        
        if (this.highPriorityIssues.length > 0) {
            console.log('\n⚠️  HIGH PRIORITY ISSUES:');
            this.highPriorityIssues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue.message}`);
            });
        }
        
        if (this.mediumPriorityIssues.length > 0) {
            console.log('\n📋 MEDIUM PRIORITY ISSUES:');
            this.mediumPriorityIssues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue.message}`);
            });
        }
        
        // Save detailed report
        const reportPath = `/var/www/mrvl-backend/focused_frontend_test_report_${Date.now()}.json`;
        fs.writeFileSync(reportPath, JSON.stringify({
            summary: {
                total_tests: this.testResults.length,
                successful: successCount,
                failed: errorCount,
                critical: this.criticalIssues.length,
                high: this.highPriorityIssues.length,
                medium: this.mediumPriorityIssues.length
            },
            issues: {
                critical: this.criticalIssues,
                high: this.highPriorityIssues,
                medium: this.mediumPriorityIssues
            },
            allResults: this.testResults
        }, null, 2));
        
        console.log(`\n📄 Detailed report saved: ${reportPath}`);
        console.log('='.repeat(60));
    }
}

// Run the focused tests
if (require.main === module) {
    const tester = new FocusedFrontendTester();
    tester.runFocusedTests().catch(error => {
        console.error('Test execution failed:', error);
        process.exit(1);
    });
}

module.exports = FocusedFrontendTester;