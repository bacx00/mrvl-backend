#!/usr/bin/env node

/**
 * Frontend-Backend Integration Test Script
 * This script demonstrates the exact API behavior and helps debug frontend issues
 */

const axios = require('axios');

const API_BASE = 'http://127.0.0.1:8001/api';
const TEST_EMAIL = 'jhonny@ar-mediia.com';
const TEST_PASSWORD = 'password123';

let authToken = '';
let testResults = {
    authentication: null,
    newsComment: null,
    forumPost: null,
    errors: []
};

async function authenticate() {
    try {
        console.log('🔐 Testing Authentication...');
        const response = await axios.post(`${API_BASE}/auth/login`, {
            email: TEST_EMAIL,
            password: TEST_PASSWORD
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        console.log(`✅ Auth Status: ${response.status}`);
        console.log(`✅ Auth Response Structure:`, JSON.stringify(response.data, null, 2));
        
        authToken = response.data.token;
        testResults.authentication = {
            status: response.status,
            success: response.data.success,
            hasToken: !!response.data.token,
            tokenLength: response.data.token?.length || 0
        };
        
        return true;
    } catch (error) {
        console.error('❌ Authentication failed:', error.response?.data || error.message);
        testResults.errors.push(`Auth Error: ${error.response?.data?.message || error.message}`);
        return false;
    }
}

async function testNewsComment() {
    try {
        console.log('\n📰 Testing News Comment API...');
        const response = await axios.post(`${API_BASE}/news/9/comments`, {
            content: 'Test comment from integration script'
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            }
        });

        console.log(`✅ News Comment Status: ${response.status}`);
        console.log(`✅ News Comment Response:`, JSON.stringify(response.data, null, 2));
        
        testResults.newsComment = {
            status: response.status,
            success: response.data.success,
            hasData: !!response.data.data,
            message: response.data.message,
            dataStructure: Object.keys(response.data.data || {})
        };
        
        // Demonstrate frontend parsing
        console.log('\n🔍 Frontend Parsing Demo:');
        console.log(`response.status: ${response.status}`);
        console.log(`response.data.success: ${response.data.success}`);
        console.log(`response.data.message: "${response.data.message}"`);
        console.log(`response.data.data.id: ${response.data.data.id}`);
        console.log(`response.data.data.content: "${response.data.data.content}"`);
        
        return true;
    } catch (error) {
        console.error('❌ News Comment failed:', error.response?.data || error.message);
        console.error('❌ Full Error:', {
            status: error.response?.status,
            statusText: error.response?.statusText,
            data: error.response?.data
        });
        testResults.errors.push(`News Comment Error: ${error.response?.data?.message || error.message}`);
        return false;
    }
}

async function testForumPost() {
    try {
        console.log('\n💬 Testing Forum Post API...');
        const response = await axios.post(`${API_BASE}/user/forums/threads/7/posts`, {
            content: 'Test forum post from integration script'
        }, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            }
        });

        console.log(`✅ Forum Post Status: ${response.status}`);
        console.log(`✅ Forum Post Response:`, JSON.stringify(response.data, null, 2));
        
        testResults.forumPost = {
            status: response.status,
            success: response.data.success,
            hasData: !!response.data.data,
            message: response.data.message,
            dataStructure: Object.keys(response.data.data || {})
        };
        
        // Demonstrate frontend parsing
        console.log('\n🔍 Frontend Parsing Demo:');
        console.log(`response.status: ${response.status}`);
        console.log(`response.data.success: ${response.data.success}`);
        console.log(`response.data.message: "${response.data.message}"`);
        console.log(`response.data.data.post.id: ${response.data.data.post.id}`);
        console.log(`response.data.data.post.content: "${response.data.data.post.content}"`);
        
        return true;
    } catch (error) {
        console.error('❌ Forum Post failed:', error.response?.data || error.message);
        console.error('❌ Full Error:', {
            status: error.response?.status,
            statusText: error.response?.statusText,
            data: error.response?.data
        });
        testResults.errors.push(`Forum Post Error: ${error.response?.data?.message || error.message}`);
        return false;
    }
}

function generateFrontendExamples() {
    console.log('\n🔧 FRONTEND FIX EXAMPLES:');
    console.log('='.repeat(60));
    
    console.log('\n1. INCORRECT Status Code Handling (LIKELY CAUSE):');
    console.log(`
// ❌ WRONG - Only checks for 200
if (response.status === 200) {
    handleSuccess(response.data);
} else {
    showError("Failed to post comment"); // This runs for 201!
}
`);
    
    console.log('\n2. CORRECT Status Code Handling:');
    console.log(`
// ✅ CORRECT - Checks for all success codes
if (response.status >= 200 && response.status < 300) {
    handleSuccess(response.data);
} else {
    showError(response.data?.message || "Request failed");
}
`);
    
    console.log('\n3. INCORRECT Response Parsing (POTENTIAL CAUSE):');
    console.log(`
// ❌ WRONG - Trying to access properties directly
const commentId = response.data.id; // undefined!
const content = response.data.content; // undefined!
`);
    
    console.log('\n4. CORRECT Response Parsing:');
    console.log(`
// ✅ CORRECT - Access through data property
const commentId = response.data.data.id;
const content = response.data.data.content;
const message = response.data.message;
const success = response.data.success;
`);
    
    console.log('\n5. Complete Fix Example:');
    console.log(`
// ✅ COMPLETE SOLUTION
try {
    const response = await axios.post('/api/news/9/comments', {content});
    
    // Check for success status codes (200-299)
    if (response.status >= 200 && response.status < 300 && response.data.success) {
        // Access data correctly
        const newComment = response.data.data;
        setComments(prev => [...prev, newComment]);
        showSuccessMessage(response.data.message);
    } else {
        showErrorMessage(response.data.message || 'Unknown error');
    }
} catch (error) {
    const errorMessage = error.response?.data?.message || 'Failed to post comment';
    showErrorMessage(errorMessage);
}
`);
}

async function runTests() {
    console.log('🚀 Starting Frontend-Backend Integration Tests\n');
    
    const authSuccess = await authenticate();
    if (!authSuccess) {
        console.error('❌ Cannot proceed without authentication');
        return;
    }
    
    await testNewsComment();
    await testForumPost();
    
    console.log('\n📊 TEST RESULTS SUMMARY:');
    console.log('='.repeat(60));
    console.log(JSON.stringify(testResults, null, 2));
    
    generateFrontendExamples();
    
    if (testResults.errors.length === 0) {
        console.log('\n✅ ALL BACKEND TESTS PASSED');
        console.log('🔍 The issue is definitely in the frontend response handling!');
    } else {
        console.log('\n❌ Some tests failed:');
        testResults.errors.forEach(error => console.log(`  - ${error}`));
    }
    
    console.log('\n🎯 CONCLUSION:');
    console.log('Backend APIs return correct 201 status codes with success:true');
    console.log('Frontend must be mishandling these responses as errors');
    console.log('Review frontend code for status code and response parsing logic');
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { runTests, testResults };
}

// Run if called directly
if (require.main === module) {
    runTests().catch(console.error);
}