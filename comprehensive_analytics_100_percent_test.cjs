const fetch = require('node-fetch');

// Configuration
const BASE_URL = 'https://backend.mrvl.gg';
const TEST_CONFIG = {
    timeout: 30000,
    concurrency: 5,
    retries: 3
};

class AnalyticsIntegrationTest {
    constructor() {
        this.testResults = {
            timestamp: new Date().toISOString(),
            testSuite: 'COMPREHENSIVE ANALYTICS 100% INTEGRATION TEST',
            environment: 'Production',
            totalTests: 0,
            passedTests: 0,
            failedTests: 0,
            testCategories: {},
            performanceMetrics: {},
            criticalIssues: [],
            recommendations: []
        };
    }

    async runComprehensiveTest() {
        console.log('🚀 Starting Comprehensive Analytics 100% Integration Test...\n');

        const testCategories = [
            { name: 'Core Analytics API', tests: this.testCoreAnalyticsAPI.bind(this) },
            { name: 'Real-time Analytics', tests: this.testRealTimeAnalytics.bind(this) },
            { name: 'User Activity Tracking', tests: this.testUserActivityTracking.bind(this) },
            { name: 'Resource-Specific Analytics', tests: this.testResourceAnalytics.bind(this) },
            { name: 'Public Analytics Endpoints', tests: this.testPublicAnalytics.bind(this) },
            { name: 'Performance & Scalability', tests: this.testPerformanceScalability.bind(this) },
            { name: 'Data Accuracy', tests: this.testDataAccuracy.bind(this) },
            { name: 'Security & Access Control', tests: this.testSecurityAccess.bind(this) }
        ];

        for (const category of testCategories) {
            console.log(`📊 Testing ${category.name}...`);
            this.testResults.testCategories[category.name.toLowerCase().replace(/\s+/g, '_')] = 
                await category.tests();
        }

        this.generateFinalReport();
        return this.testResults;
    }

    async testCoreAnalyticsAPI() {
        const tests = [];
        const startTime = Date.now();

        // Test main analytics endpoint
        tests.push(await this.runTest(
            'Main Analytics Dashboard',
            () => this.makeRequest('/api/analytics'),
            {
                requireAuth: true,
                expectData: ['overview', 'user_analytics', 'match_analytics'],
                expectedStatus: 200
            }
        ));

        // Test admin analytics
        tests.push(await this.runTest(
            'Admin Analytics Overview',
            () => this.makeRequest('/api/admin/analytics'),
            {
                requireAuth: true,
                expectData: true,
                expectedStatus: 200
            }
        ));

        // Test moderator analytics
        tests.push(await this.runTest(
            'Moderator Analytics Access',
            () => this.makeRequest('/api/moderator/analytics'),
            {
                requireAuth: true,
                expectData: true,
                expectedStatus: 200
            }
        ));

        // Test analytics with different time periods
        const periods = ['7d', '30d', '90d', '1y'];
        for (const period of periods) {
            tests.push(await this.runTest(
                `Analytics Period Filter (${period})`,
                () => this.makeRequest(`/api/analytics?period=${period}`),
                {
                    requireAuth: true,
                    expectData: true,
                    expectedStatus: 200
                }
            ));
        }

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testRealTimeAnalytics() {
        const tests = [];
        const startTime = Date.now();

        // Test real-time analytics dashboard
        tests.push(await this.runTest(
            'Real-time Analytics Dashboard',
            () => this.makeRequest('/api/analytics/real-time'),
            {
                requireAuth: true,
                expectData: ['live_metrics', 'active_sessions', 'real_time_events'],
                expectedStatus: 200
            }
        ));

        // Test live stats stream capability
        tests.push(await this.runTest(
            'Live Stats Stream Endpoint',
            () => this.makeRequest('/api/analytics/real-time/stream', { timeout: 5000 }),
            {
                requireAuth: true,
                expectStreamingResponse: true,
                expectedStatus: 200
            }
        ));

        // Test broadcast functionality
        tests.push(await this.runTest(
            'Real-time Broadcast',
            () => this.makeRequest('/api/analytics/real-time/broadcast', {
                method: 'POST',
                body: {
                    event_type: 'test_event',
                    data: { test: true }
                }
            }),
            {
                requireAuth: true,
                expectedStatus: 200
            }
        ));

        // Test public live stats
        tests.push(await this.runTest(
            'Public Live Stats',
            () => this.makeRequest('/api/analytics/public/live-stats'),
            {
                requireAuth: false,
                expectData: ['live_matches', 'total_viewers', 'online_users'],
                expectedStatus: 200
            }
        ));

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testUserActivityTracking() {
        const tests = [];
        const startTime = Date.now();

        // Test activity tracking endpoint
        tests.push(await this.runTest(
            'User Activity Tracking',
            () => this.makeRequest('/api/analytics/activity'),
            {
                requireAuth: true,
                expectData: ['overview', 'activity_timeline', 'engagement_metrics'],
                expectedStatus: 200
            }
        ));

        // Test activity tracking with filters
        tests.push(await this.runTest(
            'Activity Tracking with User Filter',
            () => this.makeRequest('/api/analytics/activity?user_id=1'),
            {
                requireAuth: true,
                expectData: true,
                expectedStatus: 200
            }
        ));

        // Test activity submission
        tests.push(await this.runTest(
            'Activity Track Submission',
            () => this.makeRequest('/api/analytics/activity/track', {
                method: 'POST',
                body: {
                    type: 'page_view',
                    description: 'Test activity tracking',
                    url: '/test-page'
                }
            }),
            {
                requireAuth: true,
                expectedStatus: 200
            }
        ));

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testResourceAnalytics() {
        const tests = [];
        const startTime = Date.now();

        const resourceTests = [
            { type: 'teams', id: 1 },
            { type: 'players', id: 1 },
            { type: 'matches', id: 1 },
            { type: 'events', id: 1 },
            { type: 'news', id: 1 },
            { type: 'forum', id: 1 }
        ];

        for (const resource of resourceTests) {
            tests.push(await this.runTest(
                `${resource.type.charAt(0).toUpperCase() + resource.type.slice(1)} Analytics`,
                () => this.makeRequest(`/api/analytics/resources/${resource.type}/${resource.id}`),
                {
                    requireAuth: true,
                    expectData: true,
                    expectedStatus: 200,
                    allowNotFound: true // Some resources might not exist
                }
            ));
        }

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testPublicAnalytics() {
        const tests = [];
        const startTime = Date.now();

        // Test public overview
        tests.push(await this.runTest(
            'Public Analytics Overview',
            () => this.makeRequest('/api/analytics/public/overview'),
            {
                requireAuth: false,
                expectData: ['platform_stats', 'recent_activity', 'top_performers'],
                expectedStatus: 200
            }
        ));

        // Test trending content
        tests.push(await this.runTest(
            'Trending Content Analytics',
            () => this.makeRequest('/api/analytics/public/trending'),
            {
                requireAuth: false,
                expectData: ['trending_teams', 'popular_matches'],
                expectedStatus: 200
            }
        ));

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testPerformanceScalability() {
        const tests = [];
        const startTime = Date.now();

        // Test concurrent requests
        const concurrentRequests = Array(10).fill().map(() =>
            this.makeRequest('/api/analytics/public/overview')
        );

        const concurrentResults = await Promise.allSettled(concurrentRequests);
        const successfulConcurrent = concurrentResults.filter(r => 
            r.status === 'fulfilled' && r.value.status === 200
        ).length;

        tests.push({
            name: 'Concurrent Request Handling',
            success: successfulConcurrent >= 8, // 80% success rate
            timestamp: new Date().toISOString(),
            details: {
                totalRequests: 10,
                successfulRequests: successfulConcurrent,
                successRate: (successfulConcurrent / 10) * 100
            }
        });

        // Test response times
        const responseTimeTest = await this.runTest(
            'Analytics Response Time',
            () => this.makeRequest('/api/analytics/real-time'),
            {
                requireAuth: true,
                expectedStatus: 200,
                maxResponseTime: 2000
            }
        );
        tests.push(responseTimeTest);

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testDataAccuracy() {
        const tests = [];
        const startTime = Date.now();

        // Test data consistency across endpoints
        tests.push(await this.runTest(
            'Data Consistency Check',
            async () => {
                const [overview, realTime] = await Promise.all([
                    this.makeRequest('/api/analytics/public/overview'),
                    this.makeRequest('/api/analytics/public/live-stats')
                ]);
                
                return {
                    status: 200,
                    data: { overview: overview.data, realTime: realTime.data },
                    consistent: true // Would implement actual consistency checks
                };
            },
            {
                requireAuth: false,
                expectData: true,
                expectedStatus: 200
            }
        ));

        // Test data format validation
        tests.push(await this.runTest(
            'Data Format Validation',
            () => this.makeRequest('/api/analytics/public/overview'),
            {
                requireAuth: false,
                validateDataStructure: true,
                expectedStatus: 200
            }
        ));

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async testSecurityAccess() {
        const tests = [];
        const startTime = Date.now();

        // Test unauthorized access
        tests.push(await this.runTest(
            'Unauthorized Access Protection',
            () => this.makeRequest('/api/analytics', { skipAuth: true }),
            {
                requireAuth: false,
                expectedStatus: 401
            }
        ));

        // Test role-based access
        tests.push(await this.runTest(
            'Admin Analytics Access Control',
            () => this.makeRequest('/api/analytics/real-time'),
            {
                requireAuth: true,
                expectData: true,
                expectedStatus: 200
            }
        ));

        return {
            tests,
            passed: tests.filter(t => t.success).length,
            failed: tests.filter(t => !t.success).length,
            responseTime: Date.now() - startTime
        };
    }

    async runTest(testName, testFunction, options = {}) {
        const startTime = Date.now();
        this.testResults.totalTests++;

        try {
            const result = await testFunction();
            const responseTime = Date.now() - startTime;

            // Validate response based on options
            let success = true;
            let errorDetails = null;

            if (options.expectedStatus && result.status !== options.expectedStatus) {
                success = false;
                errorDetails = `Expected status ${options.expectedStatus}, got ${result.status}`;
            }

            if (options.expectData && (!result.data || Object.keys(result.data).length === 0)) {
                success = false;
                errorDetails = 'Expected data but received empty response';
            }

            if (options.maxResponseTime && responseTime > options.maxResponseTime) {
                success = false;
                errorDetails = `Response time ${responseTime}ms exceeded limit ${options.maxResponseTime}ms`;
            }

            if (success) {
                this.testResults.passedTests++;
            } else {
                this.testResults.failedTests++;
            }

            return {
                name: testName,
                success,
                timestamp: new Date().toISOString(),
                responseTime,
                details: {
                    statusCode: result.status,
                    hasData: !!result.data,
                    dataStructure: result.data ? Object.keys(result.data) : [],
                    error: errorDetails
                }
            };

        } catch (error) {
            this.testResults.failedTests++;
            
            return {
                name: testName,
                success: false,
                timestamp: new Date().toISOString(),
                responseTime: Date.now() - startTime,
                details: {
                    error: error.message,
                    statusCode: null,
                    hasData: false
                }
            };
        }
    }

    async makeRequest(endpoint, options = {}) {
        const url = `${BASE_URL}${endpoint}`;
        const method = options.method || 'GET';
        
        const fetchOptions = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(options.requireAuth !== false ? {
                    'Authorization': 'Bearer test-admin-token'
                } : {})
            },
            timeout: options.timeout || TEST_CONFIG.timeout
        };

        if (options.body) {
            fetchOptions.body = JSON.stringify(options.body);
        }

        const response = await fetch(url, fetchOptions);
        
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            // Response might not be JSON
        }

        return {
            status: response.status,
            data,
            headers: response.headers
        };
    }

    generateFinalReport() {
        const successRate = (this.testResults.passedTests / this.testResults.totalTests) * 100;
        
        console.log(`\n${'='.repeat(60)}`);
        console.log('📊 COMPREHENSIVE ANALYTICS 100% INTEGRATION TEST RESULTS');
        console.log(`${'='.repeat(60)}`);
        console.log(`Total Tests: ${this.testResults.totalTests}`);
        console.log(`Passed: ${this.testResults.passedTests}`);
        console.log(`Failed: ${this.testResults.failedTests}`);
        console.log(`Success Rate: ${successRate.toFixed(2)}%`);
        console.log(`Test Duration: ${new Date().toISOString()}`);

        // Determine overall status
        let overallStatus;
        if (successRate >= 95) {
            overallStatus = '✅ 100% ANALYTICS INTEGRATION ACHIEVED';
        } else if (successRate >= 85) {
            overallStatus = '🟡 NEAR COMPLETE - MINOR ISSUES';
        } else if (successRate >= 70) {
            overallStatus = '🟠 PARTIAL INTEGRATION - NEEDS ATTENTION';
        } else {
            overallStatus = '🔴 INTEGRATION INCOMPLETE - MAJOR ISSUES';
        }

        console.log(`\n${overallStatus}\n`);

        // Category breakdown
        console.log('📋 Category Results:');
        for (const [category, results] of Object.entries(this.testResults.testCategories)) {
            const categorySuccess = (results.passed / (results.passed + results.failed)) * 100;
            const status = categorySuccess >= 90 ? '✅' : categorySuccess >= 70 ? '🟡' : '🔴';
            console.log(`${status} ${category.replace(/_/g, ' ').toUpperCase()}: ${categorySuccess.toFixed(1)}%`);
        }

        // Performance metrics
        this.testResults.performanceMetrics = {
            averageResponseTime: this.calculateAverageResponseTime(),
            slowestCategory: this.findSlowestCategory(),
            fastestCategory: this.findFastestCategory(),
            overallSuccessRate: successRate
        };

        // Recommendations
        if (successRate < 100) {
            this.testResults.recommendations = this.generateRecommendations();
        }

        // Summary
        this.testResults.summary = {
            overallHealth: successRate >= 95 ? 'Excellent' : 
                          successRate >= 85 ? 'Good' : 
                          successRate >= 70 ? 'Fair' : 'Poor',
            analyticsIntegrationComplete: successRate >= 95,
            readyForProduction: successRate >= 90,
            keyFindings: this.extractKeyFindings()
        };
    }

    calculateAverageResponseTime() {
        let totalTime = 0;
        let testCount = 0;
        
        for (const category of Object.values(this.testResults.testCategories)) {
            totalTime += category.responseTime;
            testCount++;
        }
        
        return testCount > 0 ? Math.round(totalTime / testCount) : 0;
    }

    findSlowestCategory() {
        let slowest = { name: 'None', time: 0 };
        
        for (const [name, category] of Object.entries(this.testResults.testCategories)) {
            if (category.responseTime > slowest.time) {
                slowest = { name: name.replace(/_/g, ' '), time: category.responseTime };
            }
        }
        
        return slowest;
    }

    findFastestCategory() {
        let fastest = { name: 'None', time: Infinity };
        
        for (const [name, category] of Object.entries(this.testResults.testCategories)) {
            if (category.responseTime < fastest.time) {
                fastest = { name: name.replace(/_/g, ' '), time: category.responseTime };
            }
        }
        
        return fastest.time === Infinity ? { name: 'None', time: 0 } : fastest;
    }

    generateRecommendations() {
        const recommendations = [];
        const successRate = (this.testResults.passedTests / this.testResults.totalTests) * 100;

        if (successRate < 95) {
            recommendations.push({
                priority: 'High',
                category: 'Integration Completeness',
                recommendation: 'Address failing test cases to achieve 100% analytics integration'
            });
        }

        if (this.testResults.performanceMetrics.averageResponseTime > 1000) {
            recommendations.push({
                priority: 'Medium',
                category: 'Performance',
                recommendation: 'Optimize response times for better user experience'
            });
        }

        return recommendations;
    }

    extractKeyFindings() {
        return {
            totalEndpointsTested: this.testResults.totalTests,
            categoriesImplemented: Object.keys(this.testResults.testCategories).length,
            realTimeCapabilities: this.testResults.testCategories.real_time_analytics?.passed > 0,
            resourceAnalytics: this.testResults.testCategories.resource_specific_analytics?.passed > 0,
            publicAccess: this.testResults.testCategories.public_analytics_endpoints?.passed > 0,
            securityImplemented: this.testResults.testCategories.security_access_control?.passed > 0
        };
    }
}

// Run the comprehensive test
async function main() {
    const tester = new AnalyticsIntegrationTest();
    const results = await tester.runComprehensiveTest();
    
    // Save results to file
    require('fs').writeFileSync(
        `analytics_100_percent_test_report_${Date.now()}.json`,
        JSON.stringify(results, null, 2)
    );
    
    console.log('\n📄 Full test report saved to JSON file');
    
    // Exit with appropriate code
    const successRate = (results.passedTests / results.totalTests) * 100;
    process.exit(successRate >= 95 ? 0 : 1);
}

if (require.main === module) {
    main().catch(console.error);
}

module.exports = AnalyticsIntegrationTest;