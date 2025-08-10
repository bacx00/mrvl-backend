const puppeteer = require('puppeteer');
const fs = require('fs');

class MentionSystemTest {
    constructor() {
        this.testResults = {
            timestamp: new Date().toISOString(),
            tests: [],
            summary: {
                total: 0,
                passed: 0,
                failed: 0,
                errors: []
            }
        };
        this.browser = null;
        this.page = null;
    }

    log(message, type = 'info') {
        console.log(`[${type.toUpperCase()}] ${message}`);
    }

    async init() {
        this.log('Initializing mention system test...');
        
        try {
            this.browser = await puppeteer.launch({
                headless: false,
                args: ['--no-sandbox', '--disable-setuid-sandbox'],
                defaultViewport: { width: 1920, height: 1080 }
            });
            
            this.page = await this.browser.newPage();
            
            // Set user agent
            await this.page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            
            // Enable request interception to monitor API calls
            await this.page.setRequestInterception(true);
            
            const apiCalls = [];
            this.page.on('request', (request) => {
                if (request.url().includes('/api/')) {
                    apiCalls.push({
                        url: request.url(),
                        method: request.method(),
                        timestamp: new Date().toISOString()
                    });
                }
                request.continue();
            });
            
            this.page.on('response', (response) => {
                if (response.url().includes('/api/mentions')) {
                    console.log(`API Response: ${response.status()} ${response.url()}`);
                }
            });
            
            this.log('Browser initialized successfully');
            return true;
        } catch (error) {
            this.log(`Failed to initialize browser: ${error.message}`, 'error');
            return false;
        }
    }

    async testAPI(testName, apiUrl, expectedStatus = 200) {
        this.testResults.summary.total++;
        
        try {
            this.log(`Testing API: ${testName}`);
            
            const response = await this.page.evaluate(async (url) => {
                try {
                    const response = await fetch(url);
                    return {
                        status: response.status,
                        ok: response.ok,
                        data: await response.json()
                    };
                } catch (error) {
                    return {
                        status: 0,
                        ok: false,
                        error: error.message
                    };
                }
            }, apiUrl);
            
            const success = response.status === expectedStatus && response.ok;
            
            if (success) {
                this.log(`✓ ${testName} passed - Status: ${response.status}`, 'success');
                this.testResults.summary.passed++;
            } else {
                this.log(`✗ ${testName} failed - Status: ${response.status}, Expected: ${expectedStatus}`, 'error');
                this.testResults.summary.failed++;
                this.testResults.summary.errors.push(`${testName}: Expected ${expectedStatus}, got ${response.status}`);
            }
            
            this.testResults.tests.push({
                name: testName,
                type: 'api',
                url: apiUrl,
                status: response.status,
                expected: expectedStatus,
                success: success,
                data: response.data || null,
                error: response.error || null
            });
            
            return success;
        } catch (error) {
            this.log(`✗ ${testName} failed with error: ${error.message}`, 'error');
            this.testResults.summary.failed++;
            this.testResults.summary.errors.push(`${testName}: ${error.message}`);
            
            this.testResults.tests.push({
                name: testName,
                type: 'api',
                url: apiUrl,
                success: false,
                error: error.message
            });
            
            return false;
        }
    }

    async testMentionAutocomplete() {
        this.log('Testing mention autocomplete functionality...');
        
        // Test basic mention search
        await this.testAPI(
            'Mention Search - Basic Query',
            'http://localhost:8000/api/mentions/search?q=test'
        );
        
        // Test mention search with type filter
        await this.testAPI(
            'Mention Search - User Type Filter', 
            'http://localhost:8000/api/mentions/search?q=test&type=user'
        );
        
        await this.testAPI(
            'Mention Search - Team Type Filter',
            'http://localhost:8000/api/mentions/search?q=test&type=team'
        );
        
        await this.testAPI(
            'Mention Search - Player Type Filter',
            'http://localhost:8000/api/mentions/search?q=test&type=player'
        );
        
        // Test popular mentions
        await this.testAPI(
            'Popular Mentions',
            'http://localhost:8000/api/mentions/popular'
        );
        
        // Test empty query
        await this.testAPI(
            'Mention Search - Empty Query',
            'http://localhost:8000/api/mentions/search?q='
        );
    }

    async testForumMentions() {
        this.log('Testing forum mention functionality...');
        
        try {
            await this.page.goto('http://localhost:8000', { waitUntil: 'networkidle0' });
            
            // Navigate to forum section
            const forumExists = await this.page.evaluate(() => {
                const forumLink = document.querySelector('a[href*="forum"]') || 
                                 document.querySelector('a[href*="threads"]') ||
                                 document.querySelector('[data-testid="forum-link"]');
                if (forumLink) {
                    forumLink.click();
                    return true;
                }
                return false;
            });
            
            if (forumExists) {
                await this.page.waitForTimeout(2000);
                
                // Look for text input areas (create thread, reply)
                const textareaExists = await this.page.evaluate(() => {
                    const textareas = document.querySelectorAll('textarea');
                    return textareas.length > 0;
                });
                
                if (textareaExists) {
                    // Test typing @ symbol to trigger mention dropdown
                    await this.page.focus('textarea');
                    await this.page.type('textarea', '@test', { delay: 100 });
                    
                    await this.page.waitForTimeout(1000);
                    
                    // Check if mention dropdown appears
                    const mentionDropdownExists = await this.page.evaluate(() => {
                        const dropdown = document.querySelector('.mention-dropdown') ||
                                       document.querySelector('[data-testid="mention-dropdown"]') ||
                                       document.querySelector('.autocomplete-dropdown') ||
                                       document.querySelector('[class*="mention"]');
                        return dropdown !== null;
                    });
                    
                    this.testResults.tests.push({
                        name: 'Forum Mention Dropdown',
                        type: 'frontend',
                        success: mentionDropdownExists,
                        details: mentionDropdownExists ? 'Mention dropdown appeared' : 'No mention dropdown found'
                    });
                    
                    if (mentionDropdownExists) {
                        this.log('✓ Forum mention dropdown appeared', 'success');
                        this.testResults.summary.passed++;
                    } else {
                        this.log('✗ Forum mention dropdown did not appear', 'error');
                        this.testResults.summary.failed++;
                    }
                    
                    this.testResults.summary.total++;
                }
            }
        } catch (error) {
            this.log(`Forum mention test error: ${error.message}`, 'error');
            this.testResults.tests.push({
                name: 'Forum Mention Test',
                type: 'frontend',
                success: false,
                error: error.message
            });
            this.testResults.summary.total++;
            this.testResults.summary.failed++;
        }
    }

    async testNewsMentions() {
        this.log('Testing news mention functionality...');
        
        try {
            await this.page.goto('http://localhost:8000', { waitUntil: 'networkidle0' });
            
            // Navigate to news section
            const newsExists = await this.page.evaluate(() => {
                const newsLink = document.querySelector('a[href*="news"]') || 
                               document.querySelector('[data-testid="news-link"]');
                if (newsLink) {
                    newsLink.click();
                    return true;
                }
                return false;
            });
            
            if (newsExists) {
                await this.page.waitForTimeout(2000);
                
                // Look for comment input areas
                const commentInputExists = await this.page.evaluate(() => {
                    const inputs = document.querySelectorAll('textarea, input[type="text"]');
                    return inputs.length > 0;
                });
                
                if (commentInputExists) {
                    // Test typing @ symbol to trigger mention dropdown
                    const textarea = await this.page.$('textarea');
                    if (textarea) {
                        await textarea.focus();
                        await textarea.type('@test', { delay: 100 });
                        
                        await this.page.waitForTimeout(1000);
                        
                        // Check if mention dropdown appears
                        const mentionDropdownExists = await this.page.evaluate(() => {
                            const dropdown = document.querySelector('.mention-dropdown') ||
                                           document.querySelector('[data-testid="mention-dropdown"]') ||
                                           document.querySelector('.autocomplete-dropdown') ||
                                           document.querySelector('[class*="mention"]');
                            return dropdown !== null;
                        });
                        
                        this.testResults.tests.push({
                            name: 'News Comment Mention Dropdown',
                            type: 'frontend',
                            success: mentionDropdownExists,
                            details: mentionDropdownExists ? 'Mention dropdown appeared' : 'No mention dropdown found'
                        });
                        
                        if (mentionDropdownExists) {
                            this.log('✓ News mention dropdown appeared', 'success');
                            this.testResults.summary.passed++;
                        } else {
                            this.log('✗ News mention dropdown did not appear', 'error');
                            this.testResults.summary.failed++;
                        }
                        
                        this.testResults.summary.total++;
                    }
                }
            }
        } catch (error) {
            this.log(`News mention test error: ${error.message}`, 'error');
            this.testResults.tests.push({
                name: 'News Mention Test',
                type: 'frontend',
                success: false,
                error: error.message
            });
            this.testResults.summary.total++;
            this.testResults.summary.failed++;
        }
    }

    async testMentionClickability() {
        this.log('Testing mention clickability...');
        
        try {
            await this.page.goto('http://localhost:8000', { waitUntil: 'networkidle0' });
            
            // Look for existing mentions in the page
            const mentions = await this.page.evaluate(() => {
                const mentionSelectors = [
                    'a[href*="/users/"]',
                    'a[href*="/teams/"]', 
                    'a[href*="/players/"]',
                    '.mention',
                    '[data-mention-id]',
                    'a[class*="mention"]'
                ];
                
                const mentions = [];
                mentionSelectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(el => {
                        if (el.textContent.includes('@')) {
                            mentions.push({
                                text: el.textContent,
                                href: el.href,
                                className: el.className,
                                clickable: el.tagName.toLowerCase() === 'a'
                            });
                        }
                    });
                });
                
                return mentions;
            });
            
            const clickableMentions = mentions.filter(m => m.clickable);
            
            this.testResults.tests.push({
                name: 'Mention Clickability Check',
                type: 'frontend',
                success: clickableMentions.length > 0,
                details: `Found ${mentions.length} potential mentions, ${clickableMentions.length} are clickable`,
                data: mentions.slice(0, 5) // First 5 mentions for reference
            });
            
            if (clickableMentions.length > 0) {
                this.log(`✓ Found ${clickableMentions.length} clickable mentions`, 'success');
                this.testResults.summary.passed++;
            } else {
                this.log('✗ No clickable mentions found', 'error');
                this.testResults.summary.failed++;
            }
            
            this.testResults.summary.total++;
        } catch (error) {
            this.log(`Mention clickability test error: ${error.message}`, 'error');
            this.testResults.tests.push({
                name: 'Mention Clickability Test',
                type: 'frontend',
                success: false,
                error: error.message
            });
            this.testResults.summary.total++;
            this.testResults.summary.failed++;
        }
    }

    async runAllTests() {
        this.log('Starting comprehensive mention system test...');
        
        if (!(await this.init())) {
            this.log('Failed to initialize test environment', 'error');
            return;
        }
        
        try {
            // Test API endpoints
            await this.testMentionAutocomplete();
            
            // Test frontend functionality
            await this.testForumMentions();
            await this.testNewsMentions();
            await this.testMentionClickability();
            
        } catch (error) {
            this.log(`Test execution error: ${error.message}`, 'error');
        } finally {
            await this.cleanup();
        }
    }

    async cleanup() {
        this.log('Cleaning up test environment...');
        
        if (this.browser) {
            await this.browser.close();
        }
        
        // Generate test report
        this.generateReport();
    }

    generateReport() {
        const reportPath = `mention_system_test_report_${Date.now()}.json`;
        
        this.log('Generating test report...');
        this.log(`Total tests: ${this.testResults.summary.total}`);
        this.log(`Passed: ${this.testResults.summary.passed}`);
        this.log(`Failed: ${this.testResults.summary.failed}`);
        
        if (this.testResults.summary.errors.length > 0) {
            this.log('Errors encountered:');
            this.testResults.summary.errors.forEach(error => {
                this.log(`  - ${error}`, 'error');
            });
        }
        
        const successRate = this.testResults.summary.total > 0 
            ? ((this.testResults.summary.passed / this.testResults.summary.total) * 100).toFixed(1)
            : 0;
        
        this.log(`Success rate: ${successRate}%`);
        
        // Write detailed report
        try {
            fs.writeFileSync(reportPath, JSON.stringify(this.testResults, null, 2));
            this.log(`Detailed report saved to: ${reportPath}`);
        } catch (error) {
            this.log(`Failed to save report: ${error.message}`, 'error');
        }
        
        // Generate summary report
        this.generateSummaryReport();
    }

    generateSummaryReport() {
        const summaryReport = `
=== MENTION SYSTEM TEST SUMMARY ===
Test Date: ${this.testResults.timestamp}
Total Tests: ${this.testResults.summary.total}
Passed: ${this.testResults.summary.passed}
Failed: ${this.testResults.summary.failed}
Success Rate: ${this.testResults.summary.total > 0 ? 
    ((this.testResults.summary.passed / this.testResults.summary.total) * 100).toFixed(1) : 0}%

ISSUES IDENTIFIED:
${this.testResults.summary.errors.length > 0 ? 
    this.testResults.summary.errors.map(error => `- ${error}`).join('\n') : 
    'No major issues identified'}

API TESTS:
${this.testResults.tests.filter(t => t.type === 'api').map(test => 
    `${test.success ? '✓' : '✗'} ${test.name} (${test.url})`).join('\n')}

FRONTEND TESTS:
${this.testResults.tests.filter(t => t.type === 'frontend').map(test => 
    `${test.success ? '✓' : '✗'} ${test.name} - ${test.details || test.error}`).join('\n')}

RECOMMENDATIONS:
${this.generateRecommendations()}
`;

        console.log(summaryReport);
        
        try {
            fs.writeFileSync('MENTION_SYSTEM_TEST_SUMMARY.md', summaryReport);
            this.log('Summary report saved to: MENTION_SYSTEM_TEST_SUMMARY.md');
        } catch (error) {
            this.log(`Failed to save summary: ${error.message}`, 'error');
        }
    }

    generateRecommendations() {
        const recommendations = [];
        
        const failedApiTests = this.testResults.tests.filter(t => t.type === 'api' && !t.success);
        const failedFrontendTests = this.testResults.tests.filter(t => t.type === 'frontend' && !t.success);
        
        if (failedApiTests.length > 0) {
            recommendations.push('1. Fix API endpoint issues - mention autocomplete may not be working');
        }
        
        if (failedFrontendTests.some(t => t.name.includes('Dropdown'))) {
            recommendations.push('2. Frontend mention dropdown not appearing - check JavaScript integration');
        }
        
        if (failedFrontendTests.some(t => t.name.includes('Clickability'))) {
            recommendations.push('3. Mentions are not rendering as clickable links - check mention processing');
        }
        
        if (recommendations.length === 0) {
            recommendations.push('All core functionality appears to be working correctly');
        }
        
        return recommendations.join('\n');
    }
}

// Run the test
const test = new MentionSystemTest();
test.runAllTests().catch(error => {
    console.error('Test suite failed:', error);
    process.exit(1);
});