/**
 * Direct Admin API Test Suite for MRVL Tournament Platform
 * Tests all admin functionality directly through API calls without authentication
 */

import fs from 'fs/promises';

class DirectAdminApiTester {
    constructor() {
        this.baseUrl = 'http://localhost';
        this.testResults = {
            adminOverview: { tests: [], passed: 0, failed: 0 },
            userManagement: { tests: [], passed: 0, failed: 0 },
            teamManagement: { tests: [], passed: 0, failed: 0 },
            playerManagement: { tests: [], passed: 0, failed: 0 },
            matchManagement: { tests: [], passed: 0, failed: 0 },
            eventManagement: { tests: [], passed: 0, failed: 0 },
            newsManagement: { tests: [], passed: 0, failed: 0 },
            forumManagement: { tests: [], passed: 0, failed: 0 },
            liveScoring: { tests: [], passed: 0, failed: 0 },
            bulkOperations: { tests: [], passed: 0, failed: 0 },
            analytics: { tests: [], passed: 0, failed: 0 },
            statistics: { tests: [], passed: 0, failed: 0 }
        };
        this.adminToken = 'test-admin-token'; // Placeholder for now
        this.totalTests = 0;
        this.totalPassed = 0;
        this.totalFailed = 0;
    }

    async runAllTests() {
        console.log('🚀 Starting Direct Admin API Test Suite...\n');
        
        try {
            await this.testAdminOverview();
            await this.testUserManagement();
            await this.testTeamManagement();
            await this.testPlayerManagement();
            await this.testMatchManagement();
            await this.testEventManagement();
            await this.testNewsManagement();
            await this.testForumManagement();
            await this.testLiveScoring();
            await this.testBulkOperations();
            await this.testAnalytics();
            await this.testStatistics();
            
            await this.generateReport();
            
        } catch (error) {
            console.error('❌ Test suite failed:', error);
        }
    }

    async testAdminOverview() {
        console.log('📊 Testing Admin Overview Dashboard...');
        
        const tests = [
            { name: 'Dashboard Stats', method: 'GET', endpoint: '/api/admin/dashboard' },
            { name: 'System Settings', method: 'GET', endpoint: '/api/admin/system-settings' },
            { name: 'Clear Cache', method: 'POST', endpoint: '/api/admin/clear-cache' }
        ];
        
        for (const test of tests) {
            await this.runTest('adminOverview', test);
        }
    }

    async testUserManagement() {
        console.log('\n👥 Testing User Management...');
        
        const tests = [
            { name: 'List Users', method: 'GET', endpoint: '/api/admin/users' },
            { name: 'Get Single User', method: 'GET', endpoint: '/api/admin/users/1' },
            { name: 'User Activity', method: 'GET', endpoint: '/api/admin/users/1/activity' },
            { name: 'User Management Dashboard', method: 'GET', endpoint: '/api/admin/user-management' }
        ];
        
        for (const test of tests) {
            await this.runTest('userManagement', test);
        }
    }

    async testTeamManagement() {
        console.log('\n🏆 Testing Team Management...');
        
        const tests = [
            { name: 'List Teams', method: 'GET', endpoint: '/api/teams' },
            { name: 'Get Team Details', method: 'GET', endpoint: '/api/teams/1' },
            { name: 'Team Roster', method: 'GET', endpoint: '/api/teams/1/roster' },
            { name: 'Team Statistics', method: 'GET', endpoint: '/api/teams/1/stats' }
        ];
        
        for (const test of tests) {
            await this.runTest('teamManagement', test);
        }
    }

    async testPlayerManagement() {
        console.log('\n👤 Testing Player Management...');
        
        const tests = [
            { name: 'List Players', method: 'GET', endpoint: '/api/players' },
            { name: 'Player Details', method: 'GET', endpoint: '/api/players/1' },
            { name: 'Player Statistics', method: 'GET', endpoint: '/api/players/1/stats' },
            { name: 'Player History', method: 'GET', endpoint: '/api/players/1/history' }
        ];
        
        for (const test of tests) {
            await this.runTest('playerManagement', test);
        }
    }

    async testMatchManagement() {
        console.log('\n⚔️ Testing Match Management...');
        
        const tests = [
            { name: 'List Matches', method: 'GET', endpoint: '/api/matches' },
            { name: 'Match Details', method: 'GET', endpoint: '/api/matches/1' },
            { name: 'Match Comments', method: 'GET', endpoint: '/api/matches/1/comments' },
            { name: 'Live Match Data', method: 'GET', endpoint: '/api/admin/live-scoring' }
        ];
        
        for (const test of tests) {
            await this.runTest('matchManagement', test);
        }
    }

    async testEventManagement() {
        console.log('\n🎯 Testing Event Management...');
        
        const tests = [
            { name: 'List Events', method: 'GET', endpoint: '/api/events' },
            { name: 'Event Details', method: 'GET', endpoint: '/api/events/1' },
            { name: 'Event Bracket', method: 'GET', endpoint: '/api/events/1/bracket' },
            { name: 'Event Teams', method: 'GET', endpoint: '/api/events/1/teams' }
        ];
        
        for (const test of tests) {
            await this.runTest('eventManagement', test);
        }
    }

    async testNewsManagement() {
        console.log('\n📰 Testing News Management...');
        
        const tests = [
            { name: 'List News', method: 'GET', endpoint: '/api/news' },
            { name: 'News Details', method: 'GET', endpoint: '/api/news/1' },
            { name: 'News Comments', method: 'GET', endpoint: '/api/news/1/comments' },
            { name: 'News Categories', method: 'GET', endpoint: '/api/news-categories' }
        ];
        
        for (const test of tests) {
            await this.runTest('newsManagement', test);
        }
    }

    async testForumManagement() {
        console.log('\n💬 Testing Forum Management...');
        
        const tests = [
            { name: 'Forum Categories', method: 'GET', endpoint: '/api/forum-categories' },
            { name: 'Forum Threads', method: 'GET', endpoint: '/api/forum/threads' },
            { name: 'Content Moderation', method: 'GET', endpoint: '/api/admin/content-moderation' }
        ];
        
        for (const test of tests) {
            await this.runTest('forumManagement', test);
        }
    }

    async testLiveScoring() {
        console.log('\n⚡ Testing Live Scoring System...');
        
        const tests = [
            { name: 'Live Scoring Dashboard', method: 'GET', endpoint: '/api/admin/live-scoring' },
            { name: 'Live Match Control', method: 'GET', endpoint: '/api/admin/live-scoring/1' },
            { name: 'Live Updates', method: 'GET', endpoint: '/api/live-updates' }
        ];
        
        for (const test of tests) {
            await this.runTest('liveScoring', test);
        }
    }

    async testBulkOperations() {
        console.log('\n📦 Testing Bulk Operations...');
        
        const tests = [
            { name: 'System Settings', method: 'GET', endpoint: '/api/admin/system-settings' },
            { name: 'Cache Status', method: 'GET', endpoint: '/api/admin/dashboard' },
            { name: 'Bulk Tools Access', method: 'GET', endpoint: '/api/admin/user-management' }
        ];
        
        for (const test of tests) {
            await this.runTest('bulkOperations', test);
        }
    }

    async testAnalytics() {
        console.log('\n📈 Testing Analytics Dashboard...');
        
        const tests = [
            { name: 'Analytics Dashboard (7 days)', method: 'GET', endpoint: '/api/admin/analytics?period=7days' },
            { name: 'Analytics Dashboard (30 days)', method: 'GET', endpoint: '/api/admin/analytics?period=30days' },
            { name: 'Analytics Dashboard (90 days)', method: 'GET', endpoint: '/api/admin/analytics?period=90days' },
            { name: 'Analytics Dashboard (1 year)', method: 'GET', endpoint: '/api/admin/analytics?period=1year' }
        ];
        
        for (const test of tests) {
            await this.runTest('analytics', test);
        }
    }

    async testStatistics() {
        console.log('\n📊 Testing Platform Statistics...');
        
        const tests = [
            { name: 'Platform Stats', method: 'GET', endpoint: '/api/admin/dashboard' },
            { name: 'Match Statistics', method: 'GET', endpoint: '/api/matches/stats' },
            { name: 'Event Statistics', method: 'GET', endpoint: '/api/events/stats' },
            { name: 'User Statistics', method: 'GET', endpoint: '/api/admin/user-management' }
        ];
        
        for (const test of tests) {
            await this.runTest('statistics', test);
        }
    }

    async runTest(category, test) {
        try {
            const response = await this.makeApiCall(test.method, test.endpoint, test.data);
            
            const result = {
                name: test.name,
                method: test.method,
                endpoint: test.endpoint,
                status: 'passed',
                response: response ? 'Success' : 'No Response',
                data: response || null,
                error: null
            };
            
            if (response && (response.success !== false)) {
                this.testResults[category].passed++;
                this.totalPassed++;
                console.log(`  ✅ ${test.name}`);
            } else {
                result.status = 'failed';
                result.error = 'API returned failure response';
                this.testResults[category].failed++;
                this.totalFailed++;
                console.log(`  ❌ ${test.name} - API Error`);
            }
            
            this.testResults[category].tests.push(result);
            this.totalTests++;
            
        } catch (error) {
            const result = {
                name: test.name,
                method: test.method,
                endpoint: test.endpoint,
                status: 'failed',
                response: null,
                data: null,
                error: error.message
            };
            
            this.testResults[category].tests.push(result);
            this.testResults[category].failed++;
            this.totalFailed++;
            this.totalTests++;
            
            console.log(`  ❌ ${test.name} - ${error.message}`);
        }
    }

    async makeApiCall(method, endpoint, data = null) {
        try {
            const url = `${this.baseUrl}${endpoint}`;
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.adminToken}`
                }
            };
            
            if (data && ['POST', 'PUT', 'PATCH'].includes(method)) {
                options.body = JSON.stringify(data);
            }
            
            const response = await fetch(url, options);
            
            // Handle different response types
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                const text = await response.text();
                return { 
                    success: response.ok, 
                    status: response.status, 
                    statusText: response.statusText,
                    content: text.substring(0, 200) // Limit response size
                };
            }
            
        } catch (error) {
            // Network or other errors
            throw new Error(`Network error: ${error.message}`);
        }
    }

    async generateReport() {
        console.log('\n' + '='.repeat(80));
        console.log('📊 COMPREHENSIVE ADMIN DASHBOARD TEST RESULTS');
        console.log('='.repeat(80));
        
        // Calculate totals and success rate
        const successRate = this.totalTests > 0 ? ((this.totalPassed / this.totalTests) * 100).toFixed(1) : '0.0';
        
        console.log(`\n🎯 Overall Summary:`);
        console.log(`   Total Tests: ${this.totalTests}`);
        console.log(`   Passed: ${this.totalPassed} ✅`);
        console.log(`   Failed: ${this.totalFailed} ❌`);
        console.log(`   Success Rate: ${successRate}%`);
        
        console.log(`\n📋 Category Breakdown:`);
        Object.keys(this.testResults).forEach(category => {
            const categoryData = this.testResults[category];
            const total = categoryData.tests.length;
            const rate = total > 0 ? ((categoryData.passed / total) * 100).toFixed(1) : '0.0';
            console.log(`   ${category}: ${categoryData.passed}/${total} (${rate}%) ${rate >= 80 ? '✅' : rate >= 50 ? '⚠️' : '❌'}`);
        });
        
        // Generate detailed findings
        console.log(`\n🔍 Detailed Findings:`);
        
        const workingEndpoints = [];
        const failedEndpoints = [];
        
        Object.keys(this.testResults).forEach(category => {
            this.testResults[category].tests.forEach(test => {
                if (test.status === 'passed') {
                    workingEndpoints.push(`${test.method} ${test.endpoint}`);
                } else {
                    failedEndpoints.push(`${test.method} ${test.endpoint} - ${test.error || 'Unknown error'}`);
                }
            });
        });
        
        console.log(`\n✅ Working Endpoints (${workingEndpoints.length}):`);
        workingEndpoints.slice(0, 10).forEach(endpoint => {
            console.log(`   ${endpoint}`);
        });
        if (workingEndpoints.length > 10) {
            console.log(`   ... and ${workingEndpoints.length - 10} more`);
        }
        
        console.log(`\n❌ Failed Endpoints (${failedEndpoints.length}):`);
        failedEndpoints.slice(0, 10).forEach(endpoint => {
            console.log(`   ${endpoint}`);
        });
        if (failedEndpoints.length > 10) {
            console.log(`   ... and ${failedEndpoints.length - 10} more`);
        }
        
        // Generate insights and recommendations
        console.log(`\n💡 Key Insights:`);
        
        if (successRate >= 90) {
            console.log('   ✅ Admin dashboard API is highly functional and production ready');
        } else if (successRate >= 70) {
            console.log('   ⚠️ Admin dashboard API is mostly functional with some issues to address');
        } else if (successRate >= 50) {
            console.log('   ⚠️ Admin dashboard API has moderate functionality, improvements needed');
        } else {
            console.log('   ❌ Admin dashboard API has significant issues requiring immediate attention');
        }
        
        if (this.testResults.analytics.passed > 0) {
            console.log('   ✅ Analytics functionality appears to be implemented');
        }
        
        if (this.testResults.liveScoring.passed > 0) {
            console.log('   ✅ Live scoring system has basic API endpoints');
        }
        
        if (this.testResults.userManagement.passed > 0) {
            console.log('   ✅ User management functionality is accessible');
        }
        
        console.log(`\n🔧 Recommendations:`);
        
        if (this.totalFailed > 0) {
            console.log('   📋 Review all failed endpoints and implement proper error handling');
            console.log('   🔄 Set up automated admin dashboard testing pipeline');
            console.log('   📚 Ensure all admin endpoints have proper documentation');
        }
        
        console.log('   🔒 Verify admin authentication and role-based access control');
        console.log('   📊 Implement comprehensive logging for admin actions');
        console.log('   ⚡ Monitor API performance for admin dashboard endpoints');
        console.log('   🎨 Create frontend admin components to utilize these API endpoints');
        
        // Save detailed report
        const report = {
            testSuite: 'MRVL Direct Admin API Test',
            timestamp: new Date().toISOString(),
            summary: {
                totalTests: this.totalTests,
                passed: this.totalPassed,
                failed: this.totalFailed,
                successRate: parseFloat(successRate)
            },
            categoryResults: this.testResults,
            workingEndpoints: workingEndpoints,
            failedEndpoints: failedEndpoints
        };
        
        const filename = `admin-api-test-report-${Date.now()}.json`;
        await fs.writeFile(filename, JSON.stringify(report, null, 2));
        
        console.log(`\n📊 Detailed report saved to: ${filename}`);
        console.log('='.repeat(80));
    }
}

// Run the test suite
const tester = new DirectAdminApiTester();
tester.runAllTests().catch(console.error);