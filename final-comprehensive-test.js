#!/usr/bin/env node

/**
 * FINAL COMPREHENSIVE MRVL PLATFORM TEST
 * Based on actual API routes and storage structure
 */

import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

class FinalMRVLTest {
    constructor() {
        this.results = {
            frontend: {},
            api: {},
            auth: {},
            admin: {},
            storage: {},
            fixes: {},
            summary: {
                total: 0,
                passed: 0,
                failed: 0,
                warnings: 0
            }
        };
        this.apiBaseUrl = 'http://localhost:8000';
    }

    async makeRequest(endpoint, options = {}) {
        return new Promise((resolve, reject) => {
            const url = new URL(endpoint, this.apiBaseUrl);
            const requestOptions = {
                hostname: url.hostname,
                port: url.port || 80,
                path: url.pathname + url.search,
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...options.headers
                }
            };

            const req = http.request(requestOptions, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        const jsonData = data ? JSON.parse(data) : null;
                        resolve({
                            status: res.statusCode,
                            headers: res.headers,
                            data: jsonData,
                            raw: data
                        });
                    } catch (e) {
                        resolve({
                            status: res.statusCode,
                            headers: res.headers,
                            data: null,
                            raw: data,
                            parseError: e.message
                        });
                    }
                });
            });

            req.on('error', reject);

            if (options.body) {
                req.write(JSON.stringify(options.body));
            }

            req.end();
        });
    }

    async testResult(testName, condition, details = '', category = 'general') {
        this.results.summary.total++;
        
        if (condition) {
            this.results.summary.passed++;
            console.log(`✅ ${testName}`);
            if (details) console.log(`   ${details}`);
        } else {
            this.results.summary.failed++;
            console.log(`❌ ${testName}`);
            if (details) console.log(`   ${details}`);
        }
        
        if (!this.results[category]) this.results[category] = {};
        this.results[category][testName] = {
            passed: condition,
            details: details,
            timestamp: new Date().toISOString()
        };
    }

    async testCoreAPI() {
        console.log('\n🔌 Testing Core API Functionality...\n');
        
        // Test main endpoints
        const endpoints = [
            { path: '/api/events', name: 'Events API' },
            { path: '/api/teams', name: 'Teams API' },
            { path: '/api/players', name: 'Players API' },
            { path: '/api/matches', name: 'Matches API' },
            { path: '/api/news', name: 'News API' },
            { path: '/api/heroes', name: 'Heroes API' },
            { path: '/api/forums/threads', name: 'Forums API' }
        ];

        for (const endpoint of endpoints) {
            try {
                const response = await this.makeRequest(endpoint.path);
                await this.testResult(
                    endpoint.name,
                    response.status === 200,
                    `Status: ${response.status}`,
                    'api'
                );
            } catch (error) {
                await this.testResult(
                    endpoint.name,
                    false,
                    `Error: ${error.message}`,
                    'api'
                );
            }
        }
    }

    async testAuthentication() {
        console.log('\n🔐 Testing Authentication...\n');
        
        // Test login endpoint
        try {
            const loginResponse = await this.makeRequest('/api/auth/login', {
                method: 'POST',
                body: {
                    email: 'admin@mrvl.com',
                    password: 'wrongpassword'
                }
            });
            
            await this.testResult(
                'Login Endpoint Response',
                loginResponse.status === 401 || loginResponse.status === 422,
                `Login properly rejects bad credentials: ${loginResponse.status}`,
                'auth'
            );
        } catch (error) {
            await this.testResult(
                'Login Endpoint Response',
                false,
                `Login error: ${error.message}`,
                'auth'
            );
        }

        // Test protected admin endpoints
        const adminEndpoints = [
            '/api/admin/events',
            '/api/admin/teams',
            '/api/admin/users'
        ];

        for (const endpoint of adminEndpoints) {
            try {
                const response = await this.makeRequest(endpoint);
                await this.testResult(
                    `Admin Protection: ${endpoint}`,
                    response.status === 401 || response.status === 403,
                    `Properly protected: ${response.status}`,
                    'auth'
                );
            } catch (error) {
                await this.testResult(
                    `Admin Protection: ${endpoint}`,
                    false,
                    `Error: ${error.message}`,
                    'auth'
                );
            }
        }
    }

    async testStorageStructure() {
        console.log('\n📁 Testing Storage Structure...\n');
        
        const storagePaths = [
            '../mrvl-backend/storage/app/public',
            '../mrvl-backend/storage/app/public/events',
            '../mrvl-backend/storage/app/public/teams',
            '../mrvl-backend/storage/app/public/players'
        ];
        
        let storageScore = 0;
        for (const storagePath of storagePaths) {
            const fullPath = path.resolve(__dirname, storagePath);
            const exists = fs.existsSync(fullPath);
            if (exists) storageScore++;
        }
        
        await this.testResult(
            'Storage Directory Structure',
            storageScore >= 3,
            `${storageScore}/${storagePaths.length} storage directories exist`,
            'storage'
        );

        // Check for actual event images
        try {
            const eventImagesPath = path.resolve(__dirname, '../mrvl-backend/storage/app/public/events');
            if (fs.existsSync(eventImagesPath)) {
                const imageFiles = fs.readdirSync(eventImagesPath).filter(file => 
                    file.endsWith('.jpg') || file.endsWith('.png') || file.endsWith('.jpeg')
                );
                
                await this.testResult(
                    'Event Images Available',
                    imageFiles.length > 0,
                    `Found ${imageFiles.length} event images`,
                    'storage'
                );
            }
        } catch (error) {
            await this.testResult(
                'Event Images Available',
                false,
                `Error checking images: ${error.message}`,
                'storage'
            );
        }
    }

    async testRecentFixes() {
        console.log('\n🔧 Testing Recent Fixes...\n');
        
        // Test bracket endpoints (should be event-specific, not general)
        try {
            const eventsResponse = await this.makeRequest('/api/events');
            if (eventsResponse.data && eventsResponse.data.data && eventsResponse.data.data.length > 0) {
                const firstEvent = eventsResponse.data.data[0];
                const bracketResponse = await this.makeRequest(`/api/public/events/${firstEvent.id}/bracket`);
                
                await this.testResult(
                    'Event-Specific Bracket System',
                    bracketResponse.status !== 500,
                    `Event bracket endpoint: ${bracketResponse.status}`,
                    'fixes'
                );
            } else {
                await this.testResult(
                    'Event-Specific Bracket System',
                    false,
                    'No events found to test brackets',
                    'fixes'
                );
            }
        } catch (error) {
            await this.testResult(
                'Event-Specific Bracket System',
                false,
                `Bracket test error: ${error.message}`,
                'fixes'
            );
        }

        // Test force delete route (should require auth)
        try {
            const forceDeleteResponse = await this.makeRequest('/api/admin/events/999/force', {
                method: 'DELETE'
            });
            
            await this.testResult(
                'Force Delete Authorization',
                forceDeleteResponse.status === 401 || forceDeleteResponse.status === 403,
                `Force delete properly protected: ${forceDeleteResponse.status}`,
                'fixes'
            );
        } catch (error) {
            await this.testResult(
                'Force Delete Authorization',
                false,
                `Force delete test error: ${error.message}`,
                'fixes'
            );
        }

        // Test image URL handling
        try {
            const imageResponse = await this.makeRequest('/storage/events/test.jpg');
            await this.testResult(
                'Image URL Routing',
                imageResponse.status === 404 || imageResponse.status === 200,
                `Image URLs properly handled: ${imageResponse.status}`,
                'fixes'
            );
        } catch (error) {
            await this.testResult(
                'Image URL Routing',
                false,
                `Image URL test error: ${error.message}`,
                'fixes'
            );
        }
    }

    async testDataIntegrity() {
        console.log('\n🗄️ Testing Data Integrity...\n');
        
        try {
            // Test event-team relationships
            const eventsResponse = await this.makeRequest('/api/events');
            if (eventsResponse.data && eventsResponse.data.data) {
                const events = eventsResponse.data.data;
                let hasRelationships = false;
                
                for (const event of events.slice(0, 2)) {
                    const eventDetailResponse = await this.makeRequest(`/api/events/${event.id}`);
                    if (eventDetailResponse.data && 
                        (eventDetailResponse.data.teams || 
                         (eventDetailResponse.data.data && eventDetailResponse.data.data.teams))) {
                        hasRelationships = true;
                        break;
                    }
                }
                
                await this.testResult(
                    'Event-Team Relationships',
                    hasRelationships || events.length === 0,
                    hasRelationships ? 'Event-team relationships working' : 'No events with teams found',
                    'api'
                );
            }
        } catch (error) {
            await this.testResult(
                'Event-Team Relationships',
                false,
                `Relationship test error: ${error.message}`,
                'api'
            );
        }
    }

    async testErrorHandling() {
        console.log('\n🚨 Testing Error Handling...\n');
        
        // Test 404 handling
        try {
            const notFoundResponse = await this.makeRequest('/api/nonexistent-endpoint');
            await this.testResult(
                '404 Error Handling',
                notFoundResponse.status === 404,
                `404s properly handled: ${notFoundResponse.status}`,
                'fixes'
            );
        } catch (error) {
            await this.testResult(
                '404 Error Handling',
                false,
                `404 test error: ${error.message}`,
                'fixes'
            );
        }

        // Test malformed request handling
        try {
            const malformedResponse = await this.makeRequest('/api/auth/login', {
                method: 'POST',
                body: { invalid: 'data' }
            });
            
            await this.testResult(
                'Malformed Request Handling',
                malformedResponse.status >= 400 && malformedResponse.status < 500,
                `Malformed requests properly handled: ${malformedResponse.status}`,
                'fixes'
            );
        } catch (error) {
            await this.testResult(
                'Malformed Request Handling',
                false,
                `Malformed request test error: ${error.message}`,
                'fixes'
            );
        }
    }

    generateReport() {
        console.log('\n📊 FINAL COMPREHENSIVE TEST REPORT');
        console.log('════════════════════════════════════════');
        
        const successRate = (this.results.summary.passed / this.results.summary.total) * 100;
        let platformStatus;
        
        if (successRate >= 90) {
            platformStatus = '🟢 EXCELLENT';
        } else if (successRate >= 75) {
            platformStatus = '🟡 GOOD';
        } else if (successRate >= 60) {
            platformStatus = '🟠 FAIR';
        } else {
            platformStatus = '🔴 NEEDS ATTENTION';
        }
        
        console.log(`🏥 Platform Status: ${platformStatus}`);
        console.log(`📊 Total Tests: ${this.results.summary.total}`);
        console.log(`✅ Passed: ${this.results.summary.passed}`);
        console.log(`❌ Failed: ${this.results.summary.failed}`);
        console.log(`📈 Success Rate: ${successRate.toFixed(1)}%`);
        
        // Category breakdown
        console.log('\n📋 CATEGORY BREAKDOWN:');
        Object.entries(this.results).forEach(([category, tests]) => {
            if (category === 'summary') return;
            
            const categoryTests = Object.values(tests);
            const passed = categoryTests.filter(test => test.passed).length;
            const total = categoryTests.length;
            
            if (total > 0) {
                const status = passed === total ? '✅' : passed > total / 2 ? '🟡' : '❌';
                console.log(`${status} ${category.toUpperCase()}: ${passed}/${total} tests passed`);
            }
        });
        
        // Working systems
        const workingSystems = [];
        const brokenSystems = [];
        
        Object.entries(this.results).forEach(([category, tests]) => {
            if (category === 'summary') return;
            
            const categoryTests = Object.values(tests);
            const passed = categoryTests.filter(test => test.passed).length;
            const total = categoryTests.length;
            
            if (total > 0) {
                if (passed === total) {
                    workingSystems.push(category.toUpperCase());
                } else if (passed < total / 2) {
                    brokenSystems.push(category.toUpperCase());
                }
            }
        });
        
        if (workingSystems.length > 0) {
            console.log('\n✅ FULLY WORKING SYSTEMS:');
            workingSystems.forEach(system => console.log(`   • ${system}`));
        }
        
        if (brokenSystems.length > 0) {
            console.log('\n❌ SYSTEMS NEEDING ATTENTION:');
            brokenSystems.forEach(system => console.log(`   • ${system}`));
        }
        
        // Recommendations
        console.log('\n🔧 RECOMMENDATIONS:');
        if (successRate >= 90) {
            console.log('   • Platform is in excellent condition');
            console.log('   • Continue monitoring and regular maintenance');
        } else if (successRate >= 75) {
            console.log('   • Platform is in good condition');
            console.log('   • Address minor issues identified');
        } else if (successRate >= 60) {
            console.log('   • Platform needs some attention');
            console.log('   • Focus on failed test categories');
        } else {
            console.log('   • Platform needs immediate attention');
            console.log('   • Review all failed systems');
            console.log('   • Consider running individual component tests');
        }
        
        // Summary
        console.log('\n🎯 SUMMARY:');
        console.log(`The MRVL platform is currently in ${platformStatus.split(' ')[1]} condition.`);
        console.log(`${this.results.summary.passed} out of ${this.results.summary.total} core systems are functioning properly.`);
        
        const reportPath = path.join(__dirname, `final-mrvl-test-report-${Date.now()}.json`);
        fs.writeFileSync(reportPath, JSON.stringify({
            timestamp: new Date().toISOString(),
            platformStatus,
            successRate: successRate.toFixed(1) + '%',
            summary: this.results.summary,
            details: this.results,
            workingSystems,
            brokenSystems
        }, null, 2));
        
        console.log(`📄 Detailed Report: ${reportPath}`);
        
        return {
            platformStatus,
            successRate,
            workingSystems,
            brokenSystems,
            summary: this.results.summary
        };
    }

    async runFullTest() {
        console.log('🚀 Starting Final MRVL Platform Comprehensive Test\n');
        
        try {
            await this.testCoreAPI();
            await this.testAuthentication();
            await this.testStorageStructure();
            await this.testRecentFixes();
            await this.testDataIntegrity();
            await this.testErrorHandling();
            
            const report = this.generateReport();
            
            console.log('\n🎉 Final Comprehensive Test Complete!');
            return report;
            
        } catch (error) {
            console.error('❌ Test failed:', error);
            throw error;
        }
    }
}

// Run the test
async function main() {
    const tester = new FinalMRVLTest();
    try {
        await tester.runFullTest();
        process.exit(0);
    } catch (error) {
        console.error('Test failed:', error);
        process.exit(1);
    }
}

main();