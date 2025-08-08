#!/usr/bin/env node

/**
 * Analytics Dashboard Integration Test
 * Tests admin analytics functionality across frontend and backend
 */

const axios = require('axios');

const BASE_URL = 'http://localhost:8000/api';
const TEST_ADMIN_TOKEN = 'Bearer YOUR_ADMIN_TOKEN_HERE';

async function testAnalyticsEndpoints() {
    console.log('🔥 ANALYTICS DASHBOARD TEST SUITE');
    console.log('=====================================');
    
    const endpoints = [
        '/admin/stats',
        '/admin/analytics?period=7d',
        '/admin/analytics?period=30d',
        '/admin/analytics?period=90d',
    ];
    
    for (const endpoint of endpoints) {
        try {
            console.log(`\n📊 Testing: ${endpoint}`);
            
            const response = await axios.get(`${BASE_URL}${endpoint}`, {
                headers: {
                    'Authorization': TEST_ADMIN_TOKEN,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });
            
            if (response.data?.success) {
                console.log(`✅ SUCCESS: ${endpoint}`);
                console.log(`📈 Data keys:`, Object.keys(response.data.data || response.data));
                
                // Check for key analytics data
                const data = response.data.data || response.data;
                if (data.overview) {
                    console.log(`👥 Users: ${data.overview.total_users || data.overview.totalUsers || 0}`);
                    console.log(`🏆 Teams: ${data.overview.total_teams || data.overview.totalTeams || 0}`);
                    console.log(`⚔️ Matches: ${data.overview.total_matches || data.overview.totalMatches || 0}`);
                }
                
                if (data.user_analytics || data.user_activity) {
                    const userAnalytics = data.user_analytics || data.user_activity;
                    console.log(`🟢 Active Users: ${userAnalytics.active_users || 0}`);
                    console.log(`📈 New Users: ${userAnalytics.new_users || 0}`);
                    console.log(`📊 Retention Rate: ${userAnalytics.user_retention_rate || 0}%`);
                }
                
            } else {
                console.log(`❌ FAILED: ${endpoint} - No success flag`);
                console.log(`📄 Response:`, response.data);
            }
            
        } catch (error) {
            console.log(`🚨 ERROR: ${endpoint}`);
            console.log(`📱 Status: ${error.response?.status || 'Network Error'}`);
            console.log(`💬 Message: ${error.response?.data?.message || error.message}`);
            
            if (error.response?.status === 401) {
                console.log(`🔐 HINT: Update TEST_ADMIN_TOKEN with valid admin token`);
            } else if (error.response?.status === 403) {
                console.log(`🔒 HINT: User may not have admin/moderator role`);
            }
        }
    }
    
    console.log('\n🏁 Analytics Dashboard Test Complete');
    console.log('=====================================');
}

// Test role-based analytics access
async function testRoleBasedAccess() {
    console.log('\n🔐 ROLE-BASED ACCESS TEST');
    console.log('==========================');
    
    const testCases = [
        {
            role: 'admin',
            token: TEST_ADMIN_TOKEN,
            shouldAccess: ['/admin/stats', '/admin/analytics']
        }
        // Add moderator test case when you have a moderator token
    ];
    
    for (const testCase of testCases) {
        console.log(`\n👤 Testing ${testCase.role.toUpperCase()} access...`);
        
        for (const endpoint of testCase.shouldAccess) {
            try {
                const response = await axios.get(`${BASE_URL}${endpoint}`, {
                    headers: {
                        'Authorization': testCase.token,
                        'Content-Type': 'application/json'
                    }
                });
                
                console.log(`✅ ${testCase.role} CAN access ${endpoint}`);
                
            } catch (error) {
                const status = error.response?.status;
                if (status === 403) {
                    console.log(`❌ ${testCase.role} DENIED access to ${endpoint}`);
                } else {
                    console.log(`🚨 ${testCase.role} ERROR accessing ${endpoint}: ${status}`);
                }
            }
        }
    }
}

// Frontend integration test simulation
async function simulateFrontendFlow() {
    console.log('\n🖥️ FRONTEND INTEGRATION SIMULATION');
    console.log('===================================');
    
    // Simulate AdminDashboard loading sequence
    console.log('1️⃣ AdminDashboard mounting...');
    console.log('2️⃣ Checking user role...');
    console.log('3️⃣ Loading admin stats...');
    
    try {
        const statsResponse = await axios.get(`${BASE_URL}/admin/stats`, {
            headers: { 'Authorization': TEST_ADMIN_TOKEN }
        });
        
        console.log('✅ AdminStats component would load successfully');
        
    } catch (error) {
        console.log('❌ AdminStats component would fail to load');
        console.log(`📱 Error: ${error.response?.data?.message || error.message}`);
    }
    
    console.log('4️⃣ Loading advanced analytics...');
    
    try {
        const analyticsResponse = await axios.get(`${BASE_URL}/admin/analytics?period=30d`, {
            headers: { 'Authorization': TEST_ADMIN_TOKEN }
        });
        
        console.log('✅ AdvancedAnalytics component would load successfully');
        
    } catch (error) {
        console.log('❌ AdvancedAnalytics component would fail to load');
        console.log(`📱 Error: ${error.response?.data?.message || error.message}`);
    }
}

// Main test execution
async function runAllTests() {
    console.log('🚀 Starting Analytics Dashboard Integration Tests...\n');
    
    // Update this with a real admin token for testing
    if (TEST_ADMIN_TOKEN === 'Bearer YOUR_ADMIN_TOKEN_HERE') {
        console.log('⚠️  WARNING: Please update TEST_ADMIN_TOKEN with a real admin token');
        console.log('💡 TIP: Get token from browser developer tools after logging in as admin\n');
    }
    
    await testAnalyticsEndpoints();
    await testRoleBasedAccess();
    await simulateFrontendFlow();
    
    console.log('\n✨ All tests completed!');
    console.log('\n📋 INTEGRATION CHECKLIST:');
    console.log('□ Backend analytics endpoints responding correctly');
    console.log('□ Role-based access control working');
    console.log('□ Frontend components fetching data successfully');
    console.log('□ Data visualization displaying properly');
    console.log('□ Admin tabs switching without errors');
}

// Run tests if this file is executed directly
if (require.main === module) {
    runAllTests().catch(console.error);
}

module.exports = {
    testAnalyticsEndpoints,
    testRoleBasedAccess,
    simulateFrontendFlow,
    runAllTests
};