#!/usr/bin/env node

/**
 * FRONTEND COMPONENT VALIDATION TEST
 * ==================================
 * 
 * This script specifically tests frontend component requirements:
 * 1. TeamDetailPage achievements placement below mentions
 * 2. PlayerDetailPage current team in Team History section  
 * 3. Player History hero stats display (K, D, A, KDA, DMG, Heal, BLK)
 * 4. Hero images in match history
 * 5. Event logos in match cards
 */

const puppeteer = require('puppeteer');
const fs = require('fs');

class FrontendComponentTester {
    constructor() {
        this.results = {
            timestamp: new Date().toISOString(),
            summary: {
                totalTests: 0,
                passed: 0,
                failed: 0,
                warnings: 0
            },
            componentTests: [],
            screenshots: []
        };
        this.browser = null;
        this.page = null;
        this.baseURL = 'http://localhost:3000'; // Adjust as needed
    }

    async runAllTests() {
        console.log('🎨 Starting Frontend Component Validation Tests...\n');

        try {
            await this.setupBrowser();
            
            await this.testTeamDetailPage();
            await this.testPlayerDetailPage();
            await this.testMatchCards();
            await this.testHeroImages();
            await this.testEventLogos();
            
            await this.generateReport();
            
        } catch (error) {
            console.error('❌ Frontend testing failed:', error.message);
        } finally {
            await this.cleanup();
        }
        
        console.log('\n✅ Frontend component tests completed!');
    }

    async setupBrowser() {
        console.log('🚀 Setting up browser for component testing...');
        
        this.browser = await puppeteer.launch({
            headless: false, // Set to true for CI/CD
            defaultViewport: { width: 1920, height: 1080 },
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        
        this.page = await this.browser.newPage();
        
        // Set longer timeout for page loads
        this.page.setDefaultTimeout(30000);
        
        console.log('✅ Browser setup complete');
    }

    async testTeamDetailPage() {
        console.log('\n👥 Testing TeamDetailPage Component...');
        
        const testResult = {
            component: 'TeamDetailPage',
            tests: [],
            status: 'PASSED',
            issues: []
        };

        try {
            // Navigate to a team page (adjust ID as needed)
            await this.page.goto(`${this.baseURL}/teams/1`);
            await this.page.waitForSelector('body', { timeout: 10000 });
            
            // Test 1: Check if achievements section exists
            const achievementsTest = await this.testAchievementsPlacement();
            testResult.tests.push(achievementsTest);
            
            // Test 2: Check if mentions section exists
            const mentionsTest = await this.testMentionsSection();
            testResult.tests.push(mentionsTest);
            
            // Test 3: Verify achievements are below mentions
            const placementTest = await this.testAchievementsBelowMentions();
            testResult.tests.push(placementTest);
            
            // Take screenshot for verification
            const screenshotPath = `/var/www/mrvl-backend/team_detail_screenshot_${Date.now()}.png`;
            await this.page.screenshot({ path: screenshotPath, fullPage: true });
            this.results.screenshots.push({
                component: 'TeamDetailPage',
                path: screenshotPath,
                description: 'Team detail page with achievements placement'
            });
            
        } catch (error) {
            testResult.status = 'ERROR';
            testResult.issues.push(`Navigation failed: ${error.message}`);
            console.log(`  ❌ Team page test failed: ${error.message}`);
        }

        this.results.componentTests.push(testResult);
        this.updateSummary(testResult);
    }

    async testAchievementsPlacement() {
        const test = {
            name: 'Achievements Section Exists',
            status: 'PASSED',
            details: {}
        };

        try {
            const achievementsSelector = [
                '[data-testid="achievements"]',
                '.achievements',
                '[class*="achievement"]',
                'section:has-text("Achievements")',
                'div:has-text("Achievements")'
            ];

            let achievementsFound = false;
            for (const selector of achievementsSelector) {
                try {
                    await this.page.waitForSelector(selector, { timeout: 2000 });
                    achievementsFound = true;
                    test.details.selector = selector;
                    break;
                } catch (e) {
                    // Continue to next selector
                }
            }

            if (!achievementsFound) {
                test.status = 'FAILED';
                test.details.issue = 'Achievements section not found';
                console.log('  ❌ Achievements section not found');
            } else {
                console.log('  ✅ Achievements section found');
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testMentionsSection() {
        const test = {
            name: 'Mentions Section Exists',
            status: 'PASSED',
            details: {}
        };

        try {
            const mentionsSelectors = [
                '[data-testid="mentions"]',
                '.mentions',
                '[class*="mention"]',
                'section:has-text("Mentions")',
                'div:has-text("Mentions")'
            ];

            let mentionsFound = false;
            for (const selector of mentionsSelectors) {
                try {
                    await this.page.waitForSelector(selector, { timeout: 2000 });
                    mentionsFound = true;
                    test.details.selector = selector;
                    break;
                } catch (e) {
                    // Continue to next selector
                }
            }

            if (!mentionsFound) {
                test.status = 'WARNING';
                test.details.issue = 'Mentions section not found - may not be implemented';
                console.log('  ⚠️  Mentions section not found');
            } else {
                console.log('  ✅ Mentions section found');
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testAchievementsBelowMentions() {
        const test = {
            name: 'Achievements Below Mentions',
            status: 'PASSED',
            details: {}
        };

        try {
            // This is a complex visual test - would need specific implementation
            // For now, mark as manual verification needed
            test.status = 'MANUAL_VERIFICATION';
            test.details.note = 'Visual placement verification requires manual check or specific DOM structure';
            console.log('  📋 Achievements placement verification - manual check needed');

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testPlayerDetailPage() {
        console.log('\n👤 Testing PlayerDetailPage Component...');
        
        const testResult = {
            component: 'PlayerDetailPage',
            tests: [],
            status: 'PASSED',
            issues: []
        };

        try {
            // Navigate to a player page
            await this.page.goto(`${this.baseURL}/players/1`);
            await this.page.waitForSelector('body', { timeout: 10000 });
            
            // Test 1: Check Team History section
            const teamHistoryTest = await this.testTeamHistorySection();
            testResult.tests.push(teamHistoryTest);
            
            // Test 2: Check current team display
            const currentTeamTest = await this.testCurrentTeamDisplay();
            testResult.tests.push(currentTeamTest);
            
            // Test 3: Check hero stats display
            const heroStatsTest = await this.testHeroStatsDisplay();
            testResult.tests.push(heroStatsTest);
            
            // Take screenshot
            const screenshotPath = `/var/www/mrvl-backend/player_detail_screenshot_${Date.now()}.png`;
            await this.page.screenshot({ path: screenshotPath, fullPage: true });
            this.results.screenshots.push({
                component: 'PlayerDetailPage',
                path: screenshotPath,
                description: 'Player detail page with team history and stats'
            });
            
        } catch (error) {
            testResult.status = 'ERROR';
            testResult.issues.push(`Navigation failed: ${error.message}`);
            console.log(`  ❌ Player page test failed: ${error.message}`);
        }

        this.results.componentTests.push(testResult);
        this.updateSummary(testResult);
    }

    async testTeamHistorySection() {
        const test = {
            name: 'Team History Section',
            status: 'PASSED',
            details: {}
        };

        try {
            const historySelectors = [
                '[data-testid="team-history"]',
                '.team-history',
                '[class*="history"]',
                'section:has-text("Team History")',
                'div:has-text("Team History")'
            ];

            let historyFound = false;
            for (const selector of historySelectors) {
                try {
                    await this.page.waitForSelector(selector, { timeout: 2000 });
                    historyFound = true;
                    test.details.selector = selector;
                    break;
                } catch (e) {
                    // Continue
                }
            }

            if (!historyFound) {
                test.status = 'FAILED';
                test.details.issue = 'Team History section not found';
                console.log('  ❌ Team History section not found');
            } else {
                console.log('  ✅ Team History section found');
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testCurrentTeamDisplay() {
        const test = {
            name: 'Current Team Display',
            status: 'PASSED',
            details: {}
        };

        try {
            // Look for current team indicators
            const currentTeamSelectors = [
                '[data-current="true"]',
                '.current-team',
                '[class*="current"]',
                'span:has-text("Current")',
                'div:has-text("Current")'
            ];

            let currentTeamFound = false;
            for (const selector of currentTeamSelectors) {
                try {
                    const element = await this.page.$(selector);
                    if (element) {
                        currentTeamFound = true;
                        test.details.selector = selector;
                        break;
                    }
                } catch (e) {
                    // Continue
                }
            }

            if (!currentTeamFound) {
                test.status = 'WARNING';
                test.details.issue = 'Current team indicator not clearly visible';
                console.log('  ⚠️  Current team indicator not found');
            } else {
                console.log('  ✅ Current team indicator found');
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testHeroStatsDisplay() {
        const test = {
            name: 'Hero Stats Display (K, D, A, KDA, DMG, Heal, BLK)',
            status: 'PASSED',
            details: { statsFound: [] }
        };

        try {
            const requiredStats = ['K', 'D', 'A', 'KDA', 'DMG', 'Heal', 'BLK'];
            const foundStats = [];

            for (const stat of requiredStats) {
                try {
                    // Look for stat labels
                    const statElement = await this.page.$(`text=${stat}`);
                    if (statElement) {
                        foundStats.push(stat);
                    }
                } catch (e) {
                    // Stat not found
                }
            }

            test.details.statsFound = foundStats;
            test.details.statsRequired = requiredStats;

            if (foundStats.length < requiredStats.length * 0.7) {
                test.status = 'FAILED';
                test.details.issue = `Only ${foundStats.length}/${requiredStats.length} required stats found`;
                console.log(`  ❌ Hero stats incomplete: ${foundStats.join(', ')}`);
            } else {
                console.log(`  ✅ Hero stats found: ${foundStats.join(', ')}`);
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testMatchCards() {
        console.log('\n🎮 Testing Match Cards...');
        
        const testResult = {
            component: 'MatchCards',
            tests: [],
            status: 'PASSED',
            issues: []
        };

        try {
            // Try to find match cards on current page or navigate to matches
            await this.page.goto(`${this.baseURL}/matches`);
            await this.page.waitForSelector('body', { timeout: 10000 });
            
            const matchCardTest = await this.testMatchCardStructure();
            testResult.tests.push(matchCardTest);
            
        } catch (error) {
            testResult.status = 'ERROR';
            testResult.issues.push(`Match cards test failed: ${error.message}`);
            console.log(`  ❌ Match cards test failed: ${error.message}`);
        }

        this.results.componentTests.push(testResult);
        this.updateSummary(testResult);
    }

    async testMatchCardStructure() {
        const test = {
            name: 'Match Card Structure',
            status: 'PASSED',
            details: {}
        };

        try {
            // Look for match card containers
            const matchSelectors = [
                '[data-testid="match-card"]',
                '.match-card',
                '[class*="match"]',
                '.match-item'
            ];

            let matchCardsFound = false;
            for (const selector of matchSelectors) {
                try {
                    const elements = await this.page.$$(selector);
                    if (elements.length > 0) {
                        matchCardsFound = true;
                        test.details.selector = selector;
                        test.details.count = elements.length;
                        break;
                    }
                } catch (e) {
                    // Continue
                }
            }

            if (!matchCardsFound) {
                test.status = 'FAILED';
                test.details.issue = 'Match cards not found';
                console.log('  ❌ Match cards not found');
            } else {
                console.log(`  ✅ Match cards found: ${test.details.count} cards`);
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.summary.totalTests++;
        return test;
    }

    async testHeroImages() {
        console.log('\n🦸 Testing Hero Images Display...');
        
        const test = {
            name: 'Hero Images in Match History',
            status: 'PASSED',
            details: { imagesFound: 0, imageErrors: [] }
        };

        try {
            // Look for hero images
            const heroImageSelectors = [
                'img[src*="hero"]',
                'img[alt*="hero"]',
                '.hero-image img',
                '[class*="hero"] img'
            ];

            let totalImages = 0;
            let loadedImages = 0;

            for (const selector of heroImageSelectors) {
                try {
                    const images = await this.page.$$(selector);
                    for (const img of images) {
                        totalImages++;
                        
                        // Check if image is loaded
                        const isLoaded = await img.evaluate(img => img.complete && img.naturalHeight !== 0);
                        if (isLoaded) {
                            loadedImages++;
                        } else {
                            const src = await img.evaluate(img => img.src);
                            test.details.imageErrors.push(src);
                        }
                    }
                } catch (e) {
                    // Continue
                }
            }

            test.details.imagesFound = totalImages;
            test.details.imagesLoaded = loadedImages;

            if (totalImages === 0) {
                test.status = 'WARNING';
                test.details.issue = 'No hero images found';
                console.log('  ⚠️  No hero images found');
            } else if (loadedImages < totalImages * 0.8) {
                test.status = 'FAILED';
                test.details.issue = `${totalImages - loadedImages} images failed to load`;
                console.log(`  ❌ Hero images loading issues: ${loadedImages}/${totalImages} loaded`);
            } else {
                console.log(`  ✅ Hero images loaded: ${loadedImages}/${totalImages}`);
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.componentTests.push({
            component: 'HeroImages',
            tests: [test],
            status: test.status,
            issues: test.status !== 'PASSED' ? [test.details.issue || 'Error occurred'] : []
        });

        this.updateSummaryFromTest(test);
    }

    async testEventLogos() {
        console.log('\n🏆 Testing Event Logos Display...');
        
        const test = {
            name: 'Event Logos in Match Cards',
            status: 'PASSED',
            details: { logosFound: 0, logoErrors: [] }
        };

        try {
            // Look for event logos
            const logoSelectors = [
                'img[src*="event"]',
                'img[alt*="event"]',
                '.event-logo img',
                '[class*="event"] img',
                'img[src*="tournament"]',
                '.tournament-logo img'
            ];

            let totalLogos = 0;
            let loadedLogos = 0;

            for (const selector of logoSelectors) {
                try {
                    const images = await this.page.$$(selector);
                    for (const img of images) {
                        totalLogos++;
                        
                        const isLoaded = await img.evaluate(img => img.complete && img.naturalHeight !== 0);
                        if (isLoaded) {
                            loadedLogos++;
                        } else {
                            const src = await img.evaluate(img => img.src);
                            test.details.logoErrors.push(src);
                        }
                    }
                } catch (e) {
                    // Continue
                }
            }

            test.details.logosFound = totalLogos;
            test.details.logosLoaded = loadedLogos;

            if (totalLogos === 0) {
                test.status = 'WARNING';
                test.details.issue = 'No event logos found';
                console.log('  ⚠️  No event logos found');
            } else if (loadedLogos < totalLogos * 0.8) {
                test.status = 'FAILED';
                test.details.issue = `${totalLogos - loadedLogos} event logos failed to load`;
                console.log(`  ❌ Event logo loading issues: ${loadedLogos}/${totalLogos} loaded`);
            } else {
                console.log(`  ✅ Event logos loaded: ${loadedLogos}/${totalLogos}`);
            }

        } catch (error) {
            test.status = 'ERROR';
            test.details.error = error.message;
        }

        this.results.componentTests.push({
            component: 'EventLogos',
            tests: [test],
            status: test.status,
            issues: test.status !== 'PASSED' ? [test.details.issue || 'Error occurred'] : []
        });

        this.updateSummaryFromTest(test);
    }

    updateSummary(testResult) {
        testResult.tests.forEach(test => {
            this.updateSummaryFromTest(test);
        });
    }

    updateSummaryFromTest(test) {
        this.results.summary.totalTests++;
        
        switch (test.status) {
            case 'PASSED':
                this.results.summary.passed++;
                break;
            case 'FAILED':
            case 'ERROR':
                this.results.summary.failed++;
                break;
            case 'WARNING':
            case 'MANUAL_VERIFICATION':
                this.results.summary.warnings++;
                break;
        }
    }

    async generateReport() {
        console.log('\n📋 Generating Frontend Component Test Report...\n');

        const reportData = {
            ...this.results,
            recommendations: [
                {
                    priority: 'HIGH',
                    category: 'Component Placement',
                    recommendation: 'Verify TeamDetailPage achievements are placed below mentions section',
                    implementation: 'Add CSS order properties or restructure component hierarchy'
                },
                {
                    priority: 'HIGH',
                    category: 'Data Display',
                    recommendation: 'Ensure PlayerDetailPage shows current team clearly in Team History',
                    implementation: 'Add visual indicators (badges, highlighting) for current team'
                },
                {
                    priority: 'MEDIUM',
                    category: 'Stats Display',
                    recommendation: 'Complete hero stats display with all required metrics',
                    implementation: 'Add missing stat fields: K, D, A, KDA, DMG, Heal, BLK'
                },
                {
                    priority: 'MEDIUM',
                    category: 'Image Loading',
                    recommendation: 'Implement proper fallback for failed hero images',
                    implementation: 'Add placeholder images and error handling'
                },
                {
                    priority: 'MEDIUM',
                    category: 'Visual Elements',
                    recommendation: 'Ensure event logos display in match cards',
                    implementation: 'Add event logo components and proper image loading'
                }
            ]
        };

        // Save results
        const reportFile = `/var/www/mrvl-backend/frontend_component_test_report_${Date.now()}.json`;
        fs.writeFileSync(reportFile, JSON.stringify(reportData, null, 2));
        
        console.log(`📄 Frontend test report saved to: ${reportFile}`);
        
        // Display summary
        this.displaySummary();
    }

    displaySummary() {
        console.log('\n' + '='.repeat(80));
        console.log('🎨 FRONTEND COMPONENT TEST SUMMARY');
        console.log('='.repeat(80));
        console.log(`Total Tests: ${this.results.summary.totalTests}`);
        console.log(`✅ Passed: ${this.results.summary.passed}`);
        console.log(`❌ Failed: ${this.results.summary.failed}`);
        console.log(`⚠️  Warnings: ${this.results.summary.warnings}`);
        console.log(`📈 Success Rate: ${((this.results.summary.passed / this.results.summary.totalTests) * 100).toFixed(1)}%`);
        
        console.log('\n📸 Screenshots captured:');
        this.results.screenshots.forEach(screenshot => {
            console.log(`   📷 ${screenshot.component}: ${screenshot.description}`);
            console.log(`      Path: ${screenshot.path}`);
        });
        
        console.log('\n🎯 KEY FRONTEND PRIORITIES:');
        console.log('1. 📍 Verify achievements placement below mentions on team pages');
        console.log('2. 👤 Highlight current team in player team history');
        console.log('3. 📊 Complete hero stats display (K, D, A, KDA, DMG, Heal, BLK)');
        console.log('4. 🖼️  Ensure hero images load properly in match history');
        console.log('5. 🏆 Display event logos in match cards');
        console.log('\n✨ Frontend component testing completed!\n');
    }

    async cleanup() {
        if (this.browser) {
            await this.browser.close();
            console.log('🧹 Browser cleanup completed');
        }
    }
}

// Run the tests if this file is executed directly
if (require.main === module) {
    const tester = new FrontendComponentTester();
    tester.runAllTests().catch(error => {
        console.error('❌ Frontend test execution failed:', error);
        process.exit(1);
    });
}

module.exports = FrontendComponentTester;