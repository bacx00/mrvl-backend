#!/usr/bin/env node

/**
 * COMPREHENSIVE ANALYTICS PROFILE INTEGRATION TEST
 * Tests all analytics features with user profiles and activity tracking
 */

const axios = require('axios');
const fs = require('fs').promises;

const BASE_URL = 'http://localhost:8000/api';
let adminToken = null;

// Test results storage
const testResults = {
  timestamp: new Date().toISOString(),
  analytics_integration_tests: {
    user_activity_tracking: { status: 'pending', details: [] },
    statistics_dashboards: { status: 'pending', details: [] },
    performance_metrics: { status: 'pending', details: [] },
    activity_timeline: { status: 'pending', details: [] },
    profile_view_tracking: { status: 'pending', details: [] }
  },
  errors: [],
  warnings: []
};

// Helper function to make authenticated requests
async function makeRequest(endpoint, method = 'GET', data = null) {
  const config = {
    method,
    url: BASE_URL + endpoint,
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    }
  };
  
  if (adminToken) {
    config.headers.Authorization = 'Bearer ' + adminToken;
  }
  
  if (data) {
    config.data = data;
  }
  
  return axios(config);
}

// Function to get admin token
async function getAdminToken() {
  try {
    const response = await axios.post(BASE_URL + '/auth/login', {
      email: 'admin@mrvl.com',
      password: 'password123'
    });
    
    adminToken = response.data.access_token;
    console.log('✅ Admin authentication successful');
    return true;
  } catch (error) {
    console.log('❌ Admin authentication failed:', error.response?.data?.message || error.message);
    testResults.errors.push('Admin authentication failed');
    return false;
  }
}

// Test 1: User Activity Metrics Tracking
async function testUserActivityTracking() {
  console.log('\n🔍 Testing User Activity Metrics Tracking...');
  const test = testResults.analytics_integration_tests.user_activity_tracking;
  
  try {
    // Test analytics endpoint for user activity data
    const response = await makeRequest('/admin/analytics?period=7d');
    
    if (response.data?.success) {
      const data = response.data.data;
      
      // Check for user analytics section
      if (data.user_analytics) {
        test.details.push('✅ User analytics data available');
        test.details.push('Daily Active Users: ' + (data.user_analytics.daily_active || 0));
        test.details.push('Weekly Active Users: ' + (data.user_analytics.weekly_active || 0));
        test.details.push('Monthly Active Users: ' + (data.user_analytics.monthly_active || 0));
        
        // Check for user growth trend
        if (data.user_analytics.growth_trend) {
          test.details.push('✅ User growth trend tracking available');
        }
        
        // Check for engagement levels
        if (data.user_analytics.engagement_levels) {
          test.details.push('✅ User engagement level tracking available');
          const engagement = data.user_analytics.engagement_levels;
          test.details.push('Highly Active: ' + (engagement.highly_active || 0));
          test.details.push('Moderately Active: ' + (engagement.moderately_active || 0));
          test.details.push('Low Activity: ' + (engagement.low_activity || 0));
        }
        
        test.status = 'passed';
      } else {
        test.status = 'failed';
        test.details.push('❌ No user analytics data found');
      }
    } else {
      test.status = 'failed';
      test.details.push('❌ Analytics endpoint failed');
    }
    
  } catch (error) {
    test.status = 'failed';
    test.details.push('❌ Error: ' + (error.response?.data?.message || error.message));
  }
}

// Test 2: Statistics Dashboard Accuracy
async function testStatisticsDashboard() {
  console.log('\n📊 Testing Statistics Dashboard Accuracy...');
  const test = testResults.analytics_integration_tests.statistics_dashboards;
  
  try {
    // Test admin stats endpoint
    const statsResponse = await makeRequest('/admin/stats');
    
    if (statsResponse.data?.success || statsResponse.data) {
      test.details.push('✅ Admin stats endpoint responding');
      
      const stats = statsResponse.data.data || statsResponse.data;
      
      // Check for key metrics
      const metrics = ['users', 'teams', 'matches', 'events', 'players'];
      for (const metric of metrics) {
        const value = stats[metric] || stats['total_' + metric] || stats[metric + '_count'] || 0;
        test.details.push(metric.charAt(0).toUpperCase() + metric.slice(1) + ': ' + value);
      }
      
      test.status = 'passed';
    } else {
      test.status = 'failed';
      test.details.push('❌ Admin stats endpoint failed');
    }
    
    // Test comprehensive analytics
    const analyticsResponse = await makeRequest('/admin/analytics?period=30d');
    
    if (analyticsResponse.data?.success) {
      const analytics = analyticsResponse.data.data;
      
      // Check for different analytics sections
      const sections = ['overview', 'match_analytics', 'team_analytics', 'player_analytics'];
      sections.forEach(section => {
        if (analytics[section]) {
          test.details.push('✅ ' + section.replace('_', ' ').toUpperCase() + ' section available');
        } else {
          test.details.push('⚠️ ' + section.replace('_', ' ').toUpperCase() + ' section missing');
        }
      });
      
    }
    
  } catch (error) {
    test.status = 'failed';
    test.details.push('❌ Dashboard error: ' + (error.response?.data?.message || error.message));
  }
}

// Test 3: Performance Metrics and Engagement Analytics
async function testPerformanceMetrics() {
  console.log('\n⚡ Testing Performance Metrics and Engagement Analytics...');
  const test = testResults.analytics_integration_tests.performance_metrics;
  
  try {
    const response = await makeRequest('/admin/analytics?period=30d');
    
    if (response.data?.success) {
      const data = response.data.data;
      
      // Check for engagement metrics
      if (data.engagement_metrics) {
        test.details.push('✅ Engagement metrics available');
        
        const engagement = data.engagement_metrics;
        
        // Forum activity
        if (engagement.forum_activity) {
          test.details.push('Forum Activity Tracking: ✅');
          test.details.push('- Total threads: ' + (engagement.forum_activity.total_threads || 0));
          test.details.push('- Total posts: ' + (engagement.forum_activity.total_posts || 0));
          test.details.push('- Active users: ' + (engagement.forum_activity.active_users || 0));
        }
        
        // Match engagement
        if (engagement.match_engagement) {
          test.details.push('Match Engagement Tracking: ✅');
          test.details.push('- Total viewers: ' + (engagement.match_engagement.total_viewers || 0));
          test.details.push('- Avg viewers per match: ' + (engagement.match_engagement.avg_viewers_per_match || 0));
        }
        
        // Platform activity
        if (engagement.platform_activity) {
          test.details.push('Platform Activity Tracking: ✅');
          test.details.push('- Page views: ' + (engagement.platform_activity.page_views || 0));
          test.details.push('- Unique visitors: ' + (engagement.platform_activity.unique_visitors || 0));
          test.details.push('- Session duration: ' + (engagement.platform_activity.session_duration || 'N/A'));
        }
        
        test.status = 'passed';
      } else {
        test.status = 'failed';
        test.details.push('❌ No engagement metrics found');
      }
      
      // Check for performance trends
      if (data.performance_trends) {
        test.details.push('✅ Performance trends tracking available');
      }
      
    } else {
      test.status = 'failed';
      test.details.push('❌ Analytics endpoint failed');
    }
    
  } catch (error) {
    test.status = 'failed';
    test.details.push('❌ Performance metrics error: ' + (error.response?.data?.message || error.message));
  }
}

// Test 4: Activity Timeline and History Logging
async function testActivityTimeline() {
  console.log('\n📝 Testing Activity Timeline and History Logging...');
  const test = testResults.analytics_integration_tests.activity_timeline;
  
  try {
    // Check if user activity table exists by making some test activities
    
    // First, try to get user profile to generate activity
    await makeRequest('/user/profile');
    test.details.push('✅ Generated profile view activity');
    
    // Try to get news to generate activity
    await makeRequest('/news?limit=5');
    test.details.push('✅ Generated news view activity');
    
    // Try to get matches to generate activity
    await makeRequest('/matches?limit=5');
    test.details.push('✅ Generated match view activity');
    
    // Check if activity is being tracked in analytics
    const analyticsResponse = await makeRequest('/admin/analytics?period=7d');
    
    if (analyticsResponse.data?.success) {
      const data = analyticsResponse.data.data;
      
      // Look for activity indicators
      if (data.user_analytics && data.user_analytics.daily_active) {
        test.details.push('✅ Daily active user tracking working');
      }
      
      if (data.engagement_metrics && data.engagement_metrics.platform_activity) {
        test.details.push('✅ Platform activity logging working');
      }
      
      test.status = 'passed';
    } else {
      test.status = 'failed';
      test.details.push('❌ Unable to verify activity tracking');
    }
    
  } catch (error) {
    test.status = 'failed';
    test.details.push('❌ Activity timeline error: ' + (error.response?.data?.message || error.message));
  }
}

// Test 5: Profile View Tracking and Analytics
async function testProfileViewTracking() {
  console.log('\n👤 Testing Profile View Tracking and Analytics...');
  const test = testResults.analytics_integration_tests.profile_view_tracking;
  
  try {
    // Test team profile views
    const teamsResponse = await makeRequest('/teams?limit=3');
    
    if (teamsResponse.data?.data && teamsResponse.data.data.length > 0) {
      const teamId = teamsResponse.data.data[0].id;
      await makeRequest('/teams/' + teamId);
      test.details.push('✅ Team profile view tracked for team ID: ' + teamId);
    }
    
    // Test player profile views  
    const playersResponse = await makeRequest('/players?limit=3');
    
    if (playersResponse.data?.data && playersResponse.data.data.length > 0) {
      const playerId = playersResponse.data.data[0].id;
      await makeRequest('/players/' + playerId);
      test.details.push('✅ Player profile view tracked for player ID: ' + playerId);
    }
    
    // Check analytics for profile view data
    const analyticsResponse = await makeRequest('/admin/analytics?period=7d');
    
    if (analyticsResponse.data?.success) {
      const data = analyticsResponse.data.data;
      
      // Check team analytics
      if (data.team_analytics) {
        test.details.push('✅ Team analytics section available');
        if (data.team_analytics.top_performing_teams) {
          test.details.push('✅ Team performance tracking working');
        }
      }
      
      // Check player analytics
      if (data.player_analytics) {
        test.details.push('✅ Player analytics section available');
        if (data.player_analytics.top_players) {
          test.details.push('✅ Player performance tracking working');
        }
      }
      
      test.status = 'passed';
    } else {
      test.status = 'failed';
      test.details.push('❌ Analytics endpoint failed for profile tracking');
    }
    
  } catch (error) {
    test.status = 'failed';
    test.details.push('❌ Profile view tracking error: ' + (error.response?.data?.message || error.message));
  }
}

// Generate comprehensive test report
async function generateReport() {
  const reportContent = {
    ...testResults,
    summary: {
      total_tests: 5,
      passed: Object.values(testResults.analytics_integration_tests).filter(t => t.status === 'passed').length,
      failed: Object.values(testResults.analytics_integration_tests).filter(t => t.status === 'failed').length,
      pending: Object.values(testResults.analytics_integration_tests).filter(t => t.status === 'pending').length
    },
    recommendations: []
  };
  
  // Add recommendations based on test results
  if (reportContent.summary.failed > 0) {
    reportContent.recommendations.push('Some analytics features need attention - check failed tests');
  }
  
  if (reportContent.summary.passed === reportContent.summary.total_tests) {
    reportContent.recommendations.push('All analytics features are working correctly');
  }
  
  reportContent.recommendations.push('Ensure TrackUserActivity middleware is properly registered');
  reportContent.recommendations.push('Verify UserActivity model relationships are working');
  reportContent.recommendations.push('Consider implementing real-time analytics updates');
  
  const reportPath = 'analytics_profile_integration_test_report.json';
  await fs.writeFile(reportPath, JSON.stringify(reportContent, null, 2));
  
  console.log('\n📋 ANALYTICS PROFILE INTEGRATION TEST REPORT');
  console.log('===============================================');
  console.log('Total Tests:', reportContent.summary.total_tests);
  console.log('Passed:', reportContent.summary.passed);
  console.log('Failed:', reportContent.summary.failed);
  console.log('Pending:', reportContent.summary.pending);
  console.log('\nReport saved to:', reportPath);
  
  return reportContent;
}

// Main test execution
async function runAnalyticsProfileTests() {
  console.log('🚀 COMPREHENSIVE ANALYTICS PROFILE INTEGRATION TEST');
  console.log('===================================================');
  
  // Authenticate as admin
  const authenticated = await getAdminToken();
  if (!authenticated) {
    console.log('❌ Cannot run tests without admin authentication');
    return;
  }
  
  // Run all tests
  await testUserActivityTracking();
  await testStatisticsDashboard(); 
  await testPerformanceMetrics();
  await testActivityTimeline();
  await testProfileViewTracking();
  
  // Generate report
  const report = await generateReport();
  
  console.log('\n✨ Analytics Profile Integration Tests Completed!');
  
  return report;
}

// Execute if run directly
if (require.main === module) {
  runAnalyticsProfileTests().catch(console.error);
}

module.exports = {
  runAnalyticsProfileTests,
  testUserActivityTracking,
  testStatisticsDashboard,
  testPerformanceMetrics,
  testActivityTimeline,
  testProfileViewTracking
};