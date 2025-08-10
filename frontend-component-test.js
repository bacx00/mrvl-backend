#!/usr/bin/env node

/**
 * MRVL FRONTEND COMPONENT TEST
 * Tests React frontend functionality without browser automation
 */

import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

class FrontendTester {
    constructor() {
        this.results = {
            frontend: {},
            components: {},
            routing: {},
            summary: {
                total: 0,
                passed: 0,
                failed: 0
            }
        };
        this.frontendUrl = 'http://localhost:3002';
    }

    async makeRequest(endpoint, options = {}) {
        return new Promise((resolve, reject) => {
            const url = new URL(endpoint, this.frontendUrl);
            const requestOptions = {
                hostname: url.hostname,
                port: url.port || 3002,
                path: url.pathname + url.search,
                method: options.method || 'GET',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent': 'MRVL-Test-Agent/1.0',
                    ...options.headers
                }
            };

            const req = http.request(requestOptions, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    resolve({
                        status: res.statusCode,
                        headers: res.headers,
                        data: data,
                        html: data
                    });
                });
            });

            req.on('error', reject);
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

    async testFrontendLoading() {
        console.log('\n🎨 Testing Frontend Loading...\n');
        
        try {
            const response = await this.makeRequest('/');
            
            await this.testResult(
                'Frontend Home Page Loading',
                response.status === 200,
                `Status: ${response.status}`,
                'frontend'
            );

            if (response.html) {
                // Check for React app mounting
                const hasReactRoot = response.html.includes('root') || response.html.includes('app');
                const hasViteAssets = response.html.includes('vite') || response.html.includes('.js');
                
                await this.testResult(
                    'React App Structure',
                    hasReactRoot,
                    hasReactRoot ? 'React root element found' : 'React root element not found',
                    'frontend'
                );

                await this.testResult(
                    'Vite Assets Loading',
                    hasViteAssets,
                    hasViteAssets ? 'JavaScript assets detected' : 'No JavaScript assets found',
                    'frontend'
                );
            }
        } catch (error) {
            await this.testResult(
                'Frontend Home Page Loading',
                false,
                `Error: ${error.message}`,
                'frontend'
            );
        }
    }

    async testRouting() {
        console.log('\n🗺️ Testing Frontend Routing...\n');
        
        const routes = [
            '/',
            '/events',
            '/teams',
            '/players',
            '/matches',
            '/news',
            '/forums',
            '/rankings'
        ];

        for (const route of routes) {
            try {
                const response = await this.makeRequest(route);
                
                await this.testResult(
                    `Route: ${route}`,
                    response.status === 200,
                    `Status: ${response.status}`,
                    'routing'
                );
            } catch (error) {
                await this.testResult(
                    `Route: ${route}`,
                    false,
                    `Error: ${error.message}`,
                    'routing'
                );
            }
        }
    }

    async testComponentFiles() {
        console.log('\n📦 Testing Component Files...\n');
        
        const componentPaths = [
            'src/components/pages/EventsPage.js',
            'src/components/pages/EventDetailPage.js',
            'src/app/components/EventCard.tsx',
            'src/components/BracketVisualization.js',
            'src/components/admin/AdminDashboard.js',
            'src/components/admin/EventForm.js',
            'src/components/Navigation.js'
        ];

        let componentsFound = 0;
        for (const componentPath of componentPaths) {
            const fullPath = path.resolve(__dirname, componentPath);
            const exists = fs.existsSync(fullPath);
            if (exists) componentsFound++;
        }
        
        await this.testResult(
            'Component Files Present',
            componentsFound >= componentPaths.length * 0.8,
            `${componentsFound}/${componentPaths.length} component files found`,
            'components'
        );

        // Check for event logo handling in EventCard
        try {
            const eventCardPath = path.resolve(__dirname, 'src/app/components/EventCard.tsx');
            if (fs.existsSync(eventCardPath)) {
                const content = fs.readFileSync(eventCardPath, 'utf8');
                const hasLogoHandling = content.includes('logo') || content.includes('image');
                const hasErrorHandling = content.includes('onError') || content.includes('fallback');
                
                await this.testResult(
                    'EventCard Logo Implementation',
                    hasLogoHandling,
                    hasLogoHandling ? 'Logo handling found in EventCard' : 'No logo handling in EventCard',
                    'components'
                );

                await this.testResult(
                    'EventCard Error Handling',
                    hasErrorHandling,
                    hasErrorHandling ? 'Error handling found' : 'No error handling found',
                    'components'
                );
            }
        } catch (error) {
            await this.testResult(
                'EventCard Logo Implementation',
                false,
                `Error reading EventCard: ${error.message}`,
                'components'
            );
        }
    }

    async testImageHandling() {
        console.log('\n🖼️ Testing Image Handling...\n');
        
        // Check image utilities
        const imageUtilPaths = [
            'src/utils/imageUtils.js',
            'src/utils/imageUrlUtils.js'
        ];

        let imageUtilsFound = 0;
        for (const utilPath of imageUtilPaths) {
            const fullPath = path.resolve(__dirname, utilPath);
            if (fs.existsSync(fullPath)) {
                imageUtilsFound++;
                
                try {
                    const content = fs.readFileSync(fullPath, 'utf8');
                    const hasUrlFix = content.includes('//storage') || content.includes('doubleSlash');
                    
                    await this.testResult(
                        `Image URL Fix in ${utilPath}`,
                        hasUrlFix,
                        hasUrlFix ? 'URL fixing logic found' : 'No URL fixing logic found',
                        'components'
                    );
                } catch (error) {
                    console.log(`   Error reading ${utilPath}: ${error.message}`);
                }
            }
        }
        
        await this.testResult(
            'Image Utility Files',
            imageUtilsFound > 0,
            `${imageUtilsFound}/${imageUtilPaths.length} image utility files found`,
            'components'
        );
    }

    generateFrontendReport() {
        console.log('\n📊 FRONTEND TEST REPORT');
        console.log('═══════════════════════════');
        
        const successRate = (this.results.summary.passed / this.results.summary.total) * 100;
        let frontendStatus;
        
        if (successRate >= 90) {
            frontendStatus = '🟢 EXCELLENT';
        } else if (successRate >= 75) {
            frontendStatus = '🟡 GOOD';
        } else if (successRate >= 60) {
            frontendStatus = '🟠 FAIR';
        } else {
            frontendStatus = '🔴 NEEDS ATTENTION';
        }
        
        console.log(`🎨 Frontend Status: ${frontendStatus}`);
        console.log(`📊 Total Tests: ${this.results.summary.total}`);
        console.log(`✅ Passed: ${this.results.summary.passed}`);
        console.log(`❌ Failed: ${this.results.summary.failed}`);
        console.log(`📈 Success Rate: ${successRate.toFixed(1)}%`);
        
        // Category breakdown
        console.log('\n📋 COMPONENT BREAKDOWN:');
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
        
        const reportPath = path.join(__dirname, `frontend-test-report-${Date.now()}.json`);
        fs.writeFileSync(reportPath, JSON.stringify({
            timestamp: new Date().toISOString(),
            frontendStatus,
            successRate: successRate.toFixed(1) + '%',
            summary: this.results.summary,
            details: this.results
        }, null, 2));
        
        console.log(`📄 Frontend Report: ${reportPath}`);
        
        return {
            frontendStatus,
            successRate,
            summary: this.results.summary
        };
    }

    async runFrontendTest() {
        console.log('🎨 Starting MRVL Frontend Component Test\n');
        
        try {
            await this.testFrontendLoading();
            await this.testRouting();
            await this.testComponentFiles();
            await this.testImageHandling();
            
            const report = this.generateFrontendReport();
            
            console.log('\n🎉 Frontend Test Complete!');
            return report;
            
        } catch (error) {
            console.error('❌ Frontend test failed:', error);
            throw error;
        }
    }
}

// Run the test
async function main() {
    const tester = new FrontendTester();
    try {
        await tester.runFrontendTest();
        process.exit(0);
    } catch (error) {
        console.error('Frontend test failed:', error);
        process.exit(1);
    }
}

main();