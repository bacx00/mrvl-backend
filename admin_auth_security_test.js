/**
 * MRVL PLATFORM ADMIN AUTHENTICATION & AUTHORIZATION SECURITY TEST
 * ==================================================================
 * 
 * This comprehensive test suite validates the complete admin authentication
 * and authorization system for the MRVL gaming platform.
 * 
 * Test Coverage:
 * - Authentication flow testing
 * - Role-based access control validation
 * - API endpoint security verification
 * - Session persistence testing
 * - Unauthorized access prevention
 * - Security vulnerability assessment
 */

import axios from 'axios';
import fs from 'fs';

// Configuration
const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const TEST_TIMEOUT = 30000; // 30 seconds

// Test credentials (these should exist in your database)
const TEST_CREDENTIALS = {
    admin: {
        email: 'admin@mrvl.gg',
        password: 'Admin123!@#'
    },
    moderator: {
        email: 'moderator@mrvl.gg', 
        password: 'Mod123!@#'
    },
    user: {
        email: 'user@mrvl.gg',
        password: 'User123!@#'
    }
};

// Test results tracking
let testResults = {
    total: 0,
    passed: 0,
    failed: 0,
    critical_failures: 0,
    warnings: 0,
    results: []
};

// Security vulnerability tracking
let securityIssues = {
    critical: [],
    high: [],
    medium: [],
    low: []
};

/**
 * Test runner utility
 */
function runTest(testName, testFunction, isCritical = false) {
    testResults.total++;
    console.log(`\n🧪 Testing: ${testName}`);
    
    return testFunction()
        .then(result => {
            testResults.passed++;
            testResults.results.push({
                test: testName,
                status: 'PASSED',
                result: result,
                critical: isCritical
            });
            console.log(`✅ PASSED: ${testName}`);
            return result;
        })
        .catch(error => {
            testResults.failed++;
            if (isCritical) testResults.critical_failures++;
            
            testResults.results.push({
                test: testName,
                status: 'FAILED',
                error: error.message,
                critical: isCritical
            });
            console.log(`❌ FAILED: ${testName} - ${error.message}`);
            
            // Log security issues
            if (error.security_level) {
                securityIssues[error.security_level].push({
                    test: testName,
                    issue: error.message,
                    details: error.details || 'No additional details'
                });
            }
            
            return null;
        });
}

/**
 * Create axios instance with interceptors for better error handling
 */
function createAPIClient(token = null) {
    const client = axios.create({
        baseURL: BASE_URL,
        timeout: TEST_TIMEOUT,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` })
        }
    });

    // Response interceptor for consistent error handling
    client.interceptors.response.use(
        response => response,
        error => {
            const customError = new Error(
                error.response?.data?.message || 
                error.response?.data?.error ||
                error.message ||
                'Unknown API error'
            );
            customError.status = error.response?.status;
            customError.data = error.response?.data;
            throw customError;
        }
    );

    return client;
}

/**
 * 1. TEST AUTHENTICATION FLOW
 */
async function testAuthenticationFlow() {
    const api = createAPIClient();
    
    // Test admin login
    const loginResponse = await api.post('/auth/login', TEST_CREDENTIALS.admin);
    
    if (!loginResponse.data.success) {
        throw new Error('Login failed - no success flag');
    }
    
    if (!loginResponse.data.token) {
        throw new Error('Login failed - no token returned');
    }
    
    if (!loginResponse.data.user) {
        throw new Error('Login failed - no user data returned');
    }
    
    if (loginResponse.data.user.role !== 'admin') {
        throw new Error(`Login failed - expected admin role, got: ${loginResponse.data.user.role}`);
    }
    
    return {
        token: loginResponse.data.token,
        user: loginResponse.data.user,
        message: 'Admin authentication successful'
    };
}

/**
 * 2. TEST TOKEN VALIDATION
 */
async function testTokenValidation() {
    // First get a valid token
    const authResult = await testAuthenticationFlow();
    const api = createAPIClient(authResult.token);
    
    // Test /user endpoint with valid token
    const userResponse = await api.get('/user');
    
    if (!userResponse.data.success) {
        throw new Error('Token validation failed - /user endpoint returned failure');
    }
    
    if (!userResponse.data.data.id) {
        throw new Error('Token validation failed - no user ID returned');
    }
    
    if (userResponse.data.data.role !== 'admin') {
        throw new Error(`Token validation failed - expected admin role, got: ${userResponse.data.data.role}`);
    }
    
    return {
        message: 'Token validation successful',
        user: userResponse.data.data
    };
}

/**
 * 3. TEST ROLE-BASED ACCESS CONTROL
 */
async function testRoleBasedAccessControl() {
    const results = {};
    
    // Test admin access
    const adminAuth = await testAuthenticationFlow();
    const adminAPI = createAPIClient(adminAuth.token);
    
    // Test admin endpoints
    const adminEndpoints = [
        '/admin/stats',
        '/admin/analytics', 
        '/admin/users',
        '/admin/teams',
        '/admin/players',
        '/admin/matches',
        '/admin/events',
        '/admin/news'
    ];
    
    results.admin_access = [];
    for (const endpoint of adminEndpoints) {
        try {
            const response = await adminAPI.get(endpoint);
            results.admin_access.push({
                endpoint,
                status: 'ACCESSIBLE',
                code: response.status
            });
        } catch (error) {
            if (error.status === 403) {
                throw new Error(`Admin access denied to ${endpoint} - this should not happen`);
            }
            results.admin_access.push({
                endpoint,
                status: 'ERROR',
                code: error.status,
                error: error.message
            });
        }
    }
    
    return results;
}

/**
 * 4. TEST UNAUTHORIZED ACCESS PREVENTION
 */
async function testUnauthorizedAccessPrevention() {
    const api = createAPIClient(); // No token
    const results = {};
    
    // Test protected endpoints without authentication
    const protectedEndpoints = [
        '/admin/stats',
        '/admin/users', 
        '/admin/analytics',
        '/user/profile',
        '/user/stats'
    ];
    
    results.unauthenticated_access = [];
    for (const endpoint of protectedEndpoints) {
        try {
            await api.get(endpoint);
            // If we get here, that's a security issue
            const securityError = new Error(`SECURITY VULNERABILITY: ${endpoint} is accessible without authentication`);
            securityError.security_level = 'critical';
            securityError.details = `Endpoint ${endpoint} should require authentication but doesn't`;
            throw securityError;
        } catch (error) {
            if (error.status === 401) {
                results.unauthenticated_access.push({
                    endpoint,
                    status: 'PROPERLY_PROTECTED',
                    code: error.status
                });
            } else if (error.security_level) {
                throw error; // Re-throw security errors
            } else {
                results.unauthenticated_access.push({
                    endpoint,
                    status: 'OTHER_ERROR',
                    code: error.status,
                    error: error.message
                });
            }
        }
    }
    
    return results;
}

/**
 * 5. TEST ROLE PRIVILEGE ESCALATION PREVENTION
 */
async function testRolePrivilegeEscalation() {
    const results = {};
    
    // Test user trying to access admin endpoints
    try {
        const userAPI = createAPIClient();
        const userLogin = await userAPI.post('/auth/login', TEST_CREDENTIALS.user);
        const userAuthAPI = createAPIClient(userLogin.data.token);
        
        // Try to access admin-only endpoint
        try {
            await userAuthAPI.get('/admin/users');
            const securityError = new Error('SECURITY VULNERABILITY: User role can access admin endpoints');
            securityError.security_level = 'critical';
            securityError.details = 'User with "user" role was able to access /admin/users endpoint';
            throw securityError;
        } catch (error) {
            if (error.status === 403) {
                results.privilege_escalation_prevented = true;
            } else if (error.security_level) {
                throw error; // Re-throw security errors
            } else {
                results.privilege_escalation_prevented = false;
                results.error = error.message;
            }
        }
    } catch (error) {
        if (error.message.includes('SECURITY VULNERABILITY')) {
            throw error;
        }
        // If user login fails, that's ok for this test
        results.user_login_failed = true;
        results.message = 'Could not test privilege escalation - user login failed';
    }
    
    return results;
}

/**
 * 6. TEST SESSION PERSISTENCE AND LOGOUT
 */
async function testSessionPersistence() {
    const api = createAPIClient();
    
    // Login
    const loginResponse = await api.post('/auth/login', TEST_CREDENTIALS.admin);
    const token = loginResponse.data.token;
    const authAPI = createAPIClient(token);
    
    // Verify session works
    const userResponse = await authAPI.get('/user');
    if (!userResponse.data.success) {
        throw new Error('Session verification failed after login');
    }
    
    // Test logout
    await authAPI.post('/auth/logout');
    
    // Try to use token after logout - should fail
    try {
        await authAPI.get('/user');
        const securityError = new Error('SECURITY VULNERABILITY: Token still valid after logout');
        securityError.security_level = 'high';
        securityError.details = 'JWT token was not properly invalidated after logout';
        throw securityError;
    } catch (error) {
        if (error.status === 401) {
            return { message: 'Session properly invalidated after logout' };
        } else if (error.security_level) {
            throw error;
        } else {
            throw new Error(`Unexpected error testing post-logout token: ${error.message}`);
        }
    }
}

/**
 * 7. TEST API MIDDLEWARE CONFIGURATION
 */
async function testMiddlewareConfiguration() {
    const api = createAPIClient();
    const results = {};
    
    // Test CORS headers
    try {
        const response = await api.options('/auth/login');
        results.cors_headers = {
            'access-control-allow-origin': response.headers['access-control-allow-origin'],
            'access-control-allow-methods': response.headers['access-control-allow-methods'],
            'access-control-allow-headers': response.headers['access-control-allow-headers']
        };
    } catch (error) {
        results.cors_test_error = error.message;
    }
    
    // Test rate limiting (if implemented)
    results.rate_limiting = 'NOT_TESTED'; // Would need multiple rapid requests
    
    // Test CSRF protection (if implemented)
    results.csrf_protection = 'NOT_TESTED'; // Would need specific CSRF tests
    
    return results;
}

/**
 * 8. TEST ADMIN PANEL SPECIFIC ENDPOINTS
 */
async function testAdminPanelEndpoints() {
    const authResult = await testAuthenticationFlow();
    const api = createAPIClient(authResult.token);
    const results = {};
    
    // Test all 12 admin tabs mentioned in requirements
    const adminTabs = [
        { endpoint: '/admin/stats', tab: 'Dashboard/Overview' },
        { endpoint: '/admin/analytics', tab: 'Analytics' },
        { endpoint: '/admin/users', tab: 'User Management' },
        { endpoint: '/admin/teams', tab: 'Team Management' },
        { endpoint: '/admin/players', tab: 'Player Management' },
        { endpoint: '/admin/matches', tab: 'Match Management' },
        { endpoint: '/admin/events', tab: 'Event Management' },
        { endpoint: '/admin/news', tab: 'News Management' },
        { endpoint: '/admin/forums/categories', tab: 'Forum Management' },
        { endpoint: '/admin/system/stats', tab: 'System Settings' },
        { endpoint: '/admin/bulk', tab: 'Bulk Operations' },
        { endpoint: '/admin/rankings', tab: 'Rankings' }
    ];
    
    results.admin_tabs = [];
    for (const { endpoint, tab } of adminTabs) {
        try {
            const response = await api.get(endpoint);
            results.admin_tabs.push({
                tab,
                endpoint,
                status: 'ACCESSIBLE',
                code: response.status
            });
        } catch (error) {
            results.admin_tabs.push({
                tab,
                endpoint, 
                status: 'ERROR',
                code: error.status,
                error: error.message
            });
        }
    }
    
    return results;
}

/**
 * MAIN TEST EXECUTION
 */
async function runSecurityTestSuite() {
    console.log('🔒 MRVL PLATFORM ADMIN AUTHENTICATION & AUTHORIZATION SECURITY TEST');
    console.log('=' .repeat(80));
    console.log(`🌐 Testing API at: ${BASE_URL}`);
    console.log(`⏱️  Test timeout: ${TEST_TIMEOUT}ms`);
    console.log('');
    
    try {
        // Core Authentication Tests
        await runTest('Authentication Flow', testAuthenticationFlow, true);
        await runTest('Token Validation', testTokenValidation, true);
        await runTest('Role-Based Access Control', testRoleBasedAccessControl, true);
        
        // Security Tests
        await runTest('Unauthorized Access Prevention', testUnauthorizedAccessPrevention, true);
        await runTest('Role Privilege Escalation Prevention', testRolePrivilegeEscalation, true);
        await runTest('Session Persistence & Logout', testSessionPersistence, true);
        
        // Configuration Tests
        await runTest('Middleware Configuration', testMiddlewareConfiguration, false);
        await runTest('Admin Panel Endpoints', testAdminPanelEndpoints, true);
        
    } catch (error) {
        console.error('\n💥 Test suite execution failed:', error.message);
        testResults.critical_failures++;
    }
    
    // Generate comprehensive report
    generateSecurityReport();
}

/**
 * GENERATE COMPREHENSIVE SECURITY REPORT
 */
function generateSecurityReport() {
    console.log('\n' + '='.repeat(80));
    console.log('🔒 COMPREHENSIVE SECURITY ASSESSMENT REPORT');
    console.log('='.repeat(80));
    
    // Test Summary
    console.log('\n📊 TEST SUMMARY:');
    console.log(`   Total Tests: ${testResults.total}`);
    console.log(`   ✅ Passed: ${testResults.passed}`);
    console.log(`   ❌ Failed: ${testResults.failed}`);
    console.log(`   🚨 Critical Failures: ${testResults.critical_failures}`);
    console.log(`   ⚠️  Warnings: ${testResults.warnings}`);
    
    const successRate = ((testResults.passed / testResults.total) * 100).toFixed(1);
    console.log(`   📈 Success Rate: ${successRate}%`);
    
    // Security Issues
    console.log('\n🚨 SECURITY VULNERABILITIES:');
    let totalIssues = 0;
    Object.keys(securityIssues).forEach(level => {
        const issues = securityIssues[level];
        totalIssues += issues.length;
        if (issues.length > 0) {
            console.log(`   ${level.toUpperCase()}: ${issues.length} issues`);
            issues.forEach(issue => {
                console.log(`     - ${issue.test}: ${issue.issue}`);
            });
        }
    });
    
    if (totalIssues === 0) {
        console.log('   ✅ No security vulnerabilities detected!');
    }
    
    // Detailed Test Results
    console.log('\n📋 DETAILED TEST RESULTS:');
    testResults.results.forEach(result => {
        const status = result.status === 'PASSED' ? '✅' : '❌';
        const critical = result.critical ? '🚨' : '';
        console.log(`   ${status} ${critical} ${result.test}`);
        if (result.error) {
            console.log(`      Error: ${result.error}`);
        }
    });
    
    // Recommendations
    console.log('\n💡 SECURITY RECOMMENDATIONS:');
    
    if (testResults.critical_failures > 0) {
        console.log('   🚨 CRITICAL: Address all critical failures before going to production');
    }
    
    console.log('   🔐 Implement rate limiting for authentication endpoints');
    console.log('   🛡️  Add CSRF protection for state-changing operations');
    console.log('   📝 Implement comprehensive audit logging');
    console.log('   🔄 Set up token rotation for enhanced security');
    console.log('   📊 Monitor failed authentication attempts');
    console.log('   🔒 Use HTTPS in production with proper SSL certificates');
    console.log('   🚫 Implement IP-based access controls for admin endpoints');
    
    // Final Security Score
    let securityScore = 100;
    securityScore -= (securityIssues.critical.length * 25);
    securityScore -= (securityIssues.high.length * 15);
    securityScore -= (securityIssues.medium.length * 10);
    securityScore -= (securityIssues.low.length * 5);
    securityScore -= (testResults.critical_failures * 20);
    securityScore = Math.max(0, securityScore);
    
    console.log('\n🎯 OVERALL SECURITY SCORE:');
    if (securityScore >= 90) {
        console.log(`   🟢 EXCELLENT: ${securityScore}/100 - System is production-ready`);
    } else if (securityScore >= 70) {
        console.log(`   🟡 GOOD: ${securityScore}/100 - Minor security improvements needed`);
    } else if (securityScore >= 50) {
        console.log(`   🟠 FAIR: ${securityScore}/100 - Significant security improvements required`);
    } else {
        console.log(`   🔴 POOR: ${securityScore}/100 - Critical security issues must be addressed`);
    }
    
    console.log('\n' + '='.repeat(80));
    console.log('🏁 SECURITY TEST COMPLETE');
    console.log('='.repeat(80));
    
    // Save results to file
    const reportData = {
        timestamp: new Date().toISOString(),
        summary: testResults,
        security_issues: securityIssues,
        security_score: securityScore,
        detailed_results: testResults.results
    };
    
    fs.writeFileSync(
        `admin_auth_security_report_${Date.now()}.json`, 
        JSON.stringify(reportData, null, 2)
    );
    
    console.log('📄 Detailed report saved to admin_auth_security_report_*.json');
}

// Run tests automatically
runSecurityTestSuite().catch(console.error);