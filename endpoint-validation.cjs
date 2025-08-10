#!/usr/bin/env node

/**
 * ENDPOINT VALIDATION TEST
 * 
 * Tests all critical endpoints to ensure they return proper status codes
 */

const { execSync } = require('child_process');
const fs = require('fs');

const BACKEND_URL = process.env.BACKEND_URL || 'http://localhost:8000';
const API_BASE = `${BACKEND_URL}/api`;

// Test results
let results = {
    total: 0,
    passed: 0,
    failed: 0,
    endpoints: []
};

// Colors
const colors = {
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    reset: '\x1b[0m',
    bold: '\x1b[1m'
};

function log(message, color = 'reset') {
    console.log(`${colors[color]}${message}${colors.reset}`);
}

// Test endpoint
async function testEndpoint(method, path, expectedStatus = 200, description = '') {
    results.total++;
    
    try {
        const url = path.startsWith('http') ? path : `${API_BASE}${path}`;
        
        const command = `curl -s -w "%{http_code}" -X ${method} -H "Accept: application/json" "${url}"`;
        const output = execSync(command, { encoding: 'utf8', timeout: 5000 });
        
        const statusCode = parseInt(output.slice(-3));
        const responseBody = output.slice(0, -3);
        
        const passed = statusCode === expectedStatus || (expectedStatus === 200 && statusCode >= 200 && statusCode < 300);
        
        const result = {
            method,
            path,
            description,
            status: statusCode,
            expected: expectedStatus,
            passed,
            response: responseBody.substring(0, 100) + (responseBody.length > 100 ? '...' : '')
        };
        
        results.endpoints.push(result);
        
        if (passed) {
            log(`✅ ${method} ${path} - ${statusCode} ${description}`, 'green');
            results.passed++;
        } else {
            log(`❌ ${method} ${path} - ${statusCode} (expected ${expectedStatus}) ${description}`, 'red');
            results.failed++;
        }
        
        return result;
        
    } catch (error) {
        const result = {
            method,
            path,
            description,
            status: 0,
            expected: expectedStatus,
            passed: false,
            error: error.message
        };
        
        results.endpoints.push(result);
        log(`❌ ${method} ${path} - ERROR: ${error.message} ${description}`, 'red');
        results.failed++;
        
        return result;
    }
}

async function runTests() {
    log('\n🔍 ENDPOINT VALIDATION TEST', 'bold');
    log('='.repeat(50), 'blue');
    
    // Test public endpoints
    log('\n📋 Testing Public Endpoints:', 'yellow');
    await testEndpoint('GET', '/teams', 200, '(Teams list)');
    await testEndpoint('GET', '/events', 200, '(Events list)');
    await testEndpoint('GET', '/matches', 200, '(Matches list)');
    await testEndpoint('GET', '/heroes', 200, '(Heroes list)');
    await testEndpoint('GET', '/heroes/roles', 200, '(Hero roles)');
    await testEndpoint('GET', '/news', 200, '(News list)');
    await testEndpoint('GET', '/rankings', 200, '(Rankings)');
    
    // Test specific endpoints that frontend uses
    log('\n🎯 Testing Critical Frontend Endpoints:', 'yellow');
    await testEndpoint('GET', '/public/teams', 200, '(Public teams)');
    await testEndpoint('GET', '/public/events', 200, '(Public events)');
    await testEndpoint('GET', '/public/matches', 200, '(Public matches)');
    
    // Test authentication endpoints
    log('\n🔐 Testing Auth Endpoints:', 'yellow');
    await testEndpoint('POST', '/auth/login', 422, '(Login - expect validation error)');
    await testEndpoint('GET', '/login', 401, '(Auth required endpoint)');
    
    // Test that admin endpoints are protected
    log('\n🛡️  Testing Protected Endpoints (should return 401):', 'yellow');
    await testEndpoint('GET', '/admin/matches', 401, '(Admin matches - should be protected)');
    await testEndpoint('POST', '/admin/matches', 401, '(Create match - should be protected)');
    
    // Generate report
    const report = {
        timestamp: new Date().toISOString(),
        backend_url: BACKEND_URL,
        summary: {
            total: results.total,
            passed: results.passed,
            failed: results.failed,
            success_rate: results.total > 0 ? ((results.passed / results.total) * 100).toFixed(1) + '%' : '0%'
        },
        endpoints: results.endpoints,
        status: results.failed === 0 ? 'ALL_ENDPOINTS_OK' : 'SOME_ENDPOINTS_FAILED'
    };
    
    // Save report
    fs.writeFileSync('endpoint-validation-report.json', JSON.stringify(report, null, 2));
    
    // Print summary
    log('\n📊 SUMMARY:', 'bold');
    log(`Total Endpoints: ${results.total}`);
    log(`Passed: ${results.passed}`, 'green');
    if (results.failed > 0) {
        log(`Failed: ${results.failed}`, 'red');
    }
    log(`Success Rate: ${report.summary.success_rate}`);
    
    if (results.failed === 0) {
        log('\n🎉 All endpoints responding correctly!', 'green');
    } else {
        log('\n⚠️  Some endpoints need attention', 'yellow');
    }
    
    log('\n📄 Report saved to: endpoint-validation-report.json', 'blue');
    
    return report;
}

// Run tests
runTests().catch(error => {
    log(`Test execution failed: ${error.message}`, 'red');
    process.exit(1);
});