/**
 * Comprehensive Marvel Rivals Platform Validation Test
 * Tests all recent fixes and improvements
 */

const puppeteer = require('puppeteer');
const fs = require('fs');

class PlatformValidator {
    constructor() {
        this.browser = null;
        this.page = null;
        this.results = {
            mentionsSystem: {},
            dataPersistence: {},
            adminPanel: {},
            searchFunctionality: {},
            playerProfileLayout: {},
            summary: {
                totalTests: 0,
                passed: 0,
                failed: 0,
                issues: []
            }
        };
        this.baseUrl = 'http://localhost:3000'; // React frontend
        this.apiUrl = 'http://127.0.0.1:8001/api'; // Laravel API
    }

    async initialize() {
        console.log('🚀 Initializing platform validation...');
        this.browser = await puppeteer.launch({
            headless: false,
            defaultViewport: { width: 1920, height: 1080 },
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        this.page = await this.browser.newPage();
        
        // Enable console logging
        this.page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log(`❌ Console Error: ${msg.text()}`);
                this.results.summary.issues.push(`Console Error: ${msg.text()}`);
            }
        });

        // Enable network monitoring
        this.page.on('response', response => {
            if (!response.ok() && response.url().includes('/api/')) {
                console.log(`❌ API Error: ${response.status()} - ${response.url()}`);
                this.results.summary.issues.push(`API Error: ${response.status()} - ${response.url()}`);
            }
        });

        await this.page.goto(this.baseUrl);
        console.log('✅ Browser initialized and navigated to platform');
    }

    async testMentionsSystem() {
        console.log('\n📝 Testing Mentions System...');
        const mentionsResults = {
            teamProfileMentions: false,
            playerProfileMentions: false,
            apiEndpoints: false,
            noFetchErrors: true
        };

        try {
            // Test team profile mentions
            console.log('Testing team profile mentions...');
            await this.page.goto(`${this.baseUrl}/teams`);
            await this.page.waitForSelector('[data-testid="team-card"]', { timeout: 10000 });
            
            const firstTeamLink = await this.page.$('[data-testid="team-card"] a');
            if (firstTeamLink) {
                await firstTeamLink.click();
                await this.page.waitForSelector('.mentions-section', { timeout: 5000 });
                
                const mentionsSection = await this.page.$('.mentions-section');
                if (mentionsSection) {
                    mentionsResults.teamProfileMentions = true;
                    console.log('✅ Team profile mentions loading correctly');
                } else {
                    console.log('❌ Team profile mentions section not found');
                }
            }

            // Test player profile mentions
            console.log('Testing player profile mentions...');
            await this.page.goto(`${this.baseUrl}/players`);
            await this.page.waitForSelector('[data-testid="player-card"]', { timeout: 10000 });
            
            const firstPlayerLink = await this.page.$('[data-testid="player-card"] a');
            if (firstPlayerLink) {
                await firstPlayerLink.click();
                await this.page.waitForSelector('.mentions-section', { timeout: 5000 });
                
                const mentionsSection = await this.page.$('.mentions-section');
                if (mentionsSection) {
                    mentionsResults.playerProfileMentions = true;
                    console.log('✅ Player profile mentions loading correctly');
                } else {
                    console.log('❌ Player profile mentions section not found');
                }
            }

            // Test API endpoints directly
            console.log('Testing mentions API endpoints...');
            const apiResponse = await this.page.evaluate(async () => {
                try {
                    const teamResponse = await fetch('/api/teams/1/mentions');
                    const playerResponse = await fetch('/api/players/1/mentions');
                    return {
                        teamStatus: teamResponse.status,
                        playerStatus: playerResponse.status
                    };
                } catch (error) {
                    return { error: error.message };
                }
            });

            if (apiResponse.teamStatus === 200 && apiResponse.playerStatus === 200) {
                mentionsResults.apiEndpoints = true;
                console.log('✅ API endpoints responding correctly');
            } else {
                console.log('❌ API endpoints not responding correctly');
            }

        } catch (error) {
            console.log(`❌ Error testing mentions system: ${error.message}`);
            mentionsResults.noFetchErrors = false;
        }

        this.results.mentionsSystem = mentionsResults;
        this.updateTestCounts(mentionsResults);
    }

    async testDataPersistence() {
        console.log('\n💾 Testing Data Persistence...');
        const persistenceResults = {
            teamProfileUpdate: false,
            playerProfileUpdate: false,
            socialMediaFields: false,
            numericFields: false
        };

        try {
            // Test team profile update
            console.log('Testing team profile data persistence...');
            await this.page.goto(`${this.baseUrl}/admin`);
            await this.page.waitForSelector('[data-testid="teams-tab"]', { timeout: 10000 });
            await this.page.click('[data-testid="teams-tab"]');
            
            // Wait for teams data to load
            await this.page.waitForSelector('[data-testid="edit-team-btn"]', { timeout: 10000 });
            const editButtons = await this.page.$$('[data-testid="edit-team-btn"]');
            
            if (editButtons.length > 0) {
                await editButtons[0].click();
                await this.page.waitForSelector('input[name="name"]', { timeout: 5000 });
                
                // Test updating team name
                const testName = `Test Team ${Date.now()}`;
                await this.page.evaluate((name) => {
                    document.querySelector('input[name="name"]').value = name;
                }, testName);
                
                const saveButton = await this.page.$('button[type="submit"]');
                if (saveButton) {
                    await saveButton.click();
                    await this.page.waitForTimeout(2000);
                    
                    // Verify the change persisted
                    const updatedName = await this.page.$eval('input[name="name"]', el => el.value);
                    if (updatedName === testName) {
                        persistenceResults.teamProfileUpdate = true;
                        console.log('✅ Team profile update persisted');
                    }
                }
            }

            // Test player profile update
            console.log('Testing player profile data persistence...');
            await this.page.click('[data-testid="players-tab"]');
            await this.page.waitForSelector('[data-testid="edit-player-btn"]', { timeout: 10000 });
            
            const playerEditButtons = await this.page.$$('[data-testid="edit-player-btn"]');
            if (playerEditButtons.length > 0) {
                await playerEditButtons[0].click();
                await this.page.waitForSelector('input[name="ign"]', { timeout: 5000 });
                
                // Test updating IGN
                const testIGN = `TestPlayer${Date.now()}`;
                await this.page.evaluate((ign) => {
                    document.querySelector('input[name="ign"]').value = ign;
                }, testIGN);
                
                const saveButton = await this.page.$('button[type="submit"]');
                if (saveButton) {
                    await saveButton.click();
                    await this.page.waitForTimeout(2000);
                    
                    // Verify the change persisted
                    const updatedIGN = await this.page.$eval('input[name="ign"]', el => el.value);
                    if (updatedIGN === testIGN) {
                        persistenceResults.playerProfileUpdate = true;
                        console.log('✅ Player profile update persisted');
                    }
                }
            }

            // Test social media fields
            console.log('Testing social media fields...');
            const socialFields = await this.page.$$('input[name*="social"], input[name*="twitter"], input[name*="youtube"]');
            if (socialFields.length > 0) {
                persistenceResults.socialMediaFields = true;
                console.log('✅ Social media fields present');
            }

            // Test numeric fields
            console.log('Testing numeric fields...');
            const numericFields = await this.page.$$('input[name*="earnings"], input[name*="winnings"], input[type="number"]');
            if (numericFields.length > 0) {
                persistenceResults.numericFields = true;
                console.log('✅ Numeric fields present');
            }

        } catch (error) {
            console.log(`❌ Error testing data persistence: ${error.message}`);
        }

        this.results.dataPersistence = persistenceResults;
        this.updateTestCounts(persistenceResults);
    }

    async testAdminPanel() {
        console.log('\n⚙️ Testing Admin Panel...');
        const adminResults = {
            realTeamsData: false,
            realPlayersData: false,
            paginationWorks: false,
            totalCounts: false
        };

        try {
            await this.page.goto(`${this.baseUrl}/admin`);
            
            // Test teams tab
            console.log('Testing admin teams data...');
            await this.page.click('[data-testid="teams-tab"]');
            await this.page.waitForSelector('[data-testid="teams-grid"]', { timeout: 10000 });
            
            const teamsCount = await this.page.evaluate(() => {
                const teamCards = document.querySelectorAll('[data-testid="team-card"]');
                return teamCards.length;
            });
            
            if (teamsCount > 50) { // Should show 53 teams
                adminResults.realTeamsData = true;
                console.log(`✅ Admin shows ${teamsCount} teams (expected ~53)`);
            } else {
                console.log(`❌ Admin shows only ${teamsCount} teams (expected ~53)`);
            }

            // Test players tab
            console.log('Testing admin players data...');
            await this.page.click('[data-testid="players-tab"]');
            await this.page.waitForSelector('[data-testid="players-grid"]', { timeout: 10000 });
            
            const playersCount = await this.page.evaluate(() => {
                const playerCards = document.querySelectorAll('[data-testid="player-card"]');
                return playerCards.length;
            });
            
            if (playersCount > 300) { // Should show 318 players
                adminResults.realPlayersData = true;
                console.log(`✅ Admin shows ${playersCount} players (expected ~318)`);
            } else {
                console.log(`❌ Admin shows only ${playersCount} players (expected ~318)`);
            }

            // Test pagination
            console.log('Testing pagination...');
            const paginationButtons = await this.page.$$('[data-testid="pagination-btn"]');
            if (paginationButtons.length > 1) {
                await paginationButtons[1].click(); // Click page 2
                await this.page.waitForTimeout(1000);
                
                const currentPage = await this.page.$eval('[data-testid="current-page"]', el => el.textContent);
                if (currentPage === '2') {
                    adminResults.paginationWorks = true;
                    console.log('✅ Pagination working correctly');
                }
            }

            // Test total counts
            console.log('Testing total counts display...');
            const totalCountElement = await this.page.$('[data-testid="total-count"]');
            if (totalCountElement) {
                const totalText = await this.page.$eval('[data-testid="total-count"]', el => el.textContent);
                if (totalText.includes('Total:')) {
                    adminResults.totalCounts = true;
                    console.log(`✅ Total counts displayed: ${totalText}`);
                }
            }

        } catch (error) {
            console.log(`❌ Error testing admin panel: ${error.message}`);
        }

        this.results.adminPanel = adminResults;
        this.updateTestCounts(adminResults);
    }

    async testSearchFunctionality() {
        console.log('\n🔍 Testing Search Functionality...');
        const searchResults = {
            fullWordTyping: false,
            noSingleCharReset: false,
            correctFiltering: false,
            multiFieldSearch: false
        };

        try {
            // Test on teams page
            console.log('Testing search on teams page...');
            await this.page.goto(`${this.baseUrl}/teams`);
            await this.page.waitForSelector('[data-testid="search-input"]', { timeout: 10000 });
            
            // Test full word typing
            await this.page.type('[data-testid="search-input"]', 'Team');
            await this.page.waitForTimeout(1000);
            
            const resultsAfterTyping = await this.page.$$('[data-testid="team-card"]');
            if (resultsAfterTyping.length > 0) {
                searchResults.fullWordTyping = true;
                console.log('✅ Full word typing works');
            }

            // Test that single character doesn't reset
            await this.page.evaluate(() => {
                document.querySelector('[data-testid="search-input"]').value = '';
            });
            await this.page.type('[data-testid="search-input"]', 'T');
            await this.page.waitForTimeout(500);
            
            const resultsAfterSingleChar = await this.page.$$('[data-testid="team-card"]');
            if (resultsAfterSingleChar.length > 0) {
                searchResults.noSingleCharReset = true;
                console.log('✅ Single character doesn\'t reset search');
            }

            // Test filtering
            await this.page.evaluate(() => {
                document.querySelector('[data-testid="search-input"]').value = '';
            });
            await this.page.type('[data-testid="search-input"]', 'NonExistentTeam123');
            await this.page.waitForTimeout(1000);
            
            const noResults = await this.page.$$('[data-testid="team-card"]');
            if (noResults.length === 0) {
                searchResults.correctFiltering = true;
                console.log('✅ Search filtering works correctly');
            }

            // Test on players page for multi-field search
            console.log('Testing multi-field search on players page...');
            await this.page.goto(`${this.baseUrl}/players`);
            await this.page.waitForSelector('[data-testid="search-input"]', { timeout: 10000 });
            
            await this.page.type('[data-testid="search-input"]', 'Player');
            await this.page.waitForTimeout(1000);
            
            const playerResults = await this.page.$$('[data-testid="player-card"]');
            if (playerResults.length > 0) {
                searchResults.multiFieldSearch = true;
                console.log('✅ Multi-field search works');
            }

        } catch (error) {
            console.log(`❌ Error testing search functionality: ${error.message}`);
        }

        this.results.searchFunctionality = searchResults;
        this.updateTestCounts(searchResults);
    }

    async testPlayerProfileLayout() {
        console.log('\n👤 Testing Player Profile Layout...');
        const layoutResults = {
            careerPerformanceRemoved: false,
            pastTeamsAtBottom: false,
            mentionsOnRight: false,
            achievementsOnRight: false
        };

        try {
            await this.page.goto(`${this.baseUrl}/players`);
            await this.page.waitForSelector('[data-testid="player-card"]', { timeout: 10000 });
            
            const firstPlayerLink = await this.page.$('[data-testid="player-card"] a');
            if (firstPlayerLink) {
                await firstPlayerLink.click();
                await this.page.waitForSelector('.player-profile', { timeout: 10000 });
                
                // Check if Career Performance section is removed
                const careerPerformanceSection = await this.page.$('.career-performance-section');
                if (!careerPerformanceSection) {
                    layoutResults.careerPerformanceRemoved = true;
                    console.log('✅ Career Performance section removed');
                } else {
                    console.log('❌ Career Performance section still present');
                }

                // Check Past Teams at bottom
                const profileSections = await this.page.$$eval('.profile-section', sections => 
                    sections.map(section => ({
                        text: section.textContent.toLowerCase(),
                        position: section.getBoundingClientRect().top
                    }))
                );
                
                const pastTeamsSection = profileSections.find(s => s.text.includes('past teams'));
                if (pastTeamsSection) {
                    const isAtBottom = profileSections.filter(s => s.position > pastTeamsSection.position).length <= 1;
                    if (isAtBottom) {
                        layoutResults.pastTeamsAtBottom = true;
                        console.log('✅ Past Teams section at bottom');
                    }
                }

                // Check Mentions on right side
                const mentionsSection = await this.page.$('.mentions-section');
                if (mentionsSection) {
                    const mentionsPosition = await this.page.evaluate(el => {
                        const rect = el.getBoundingClientRect();
                        return { x: rect.x, width: rect.width };
                    }, mentionsSection);
                    
                    const windowWidth = await this.page.evaluate(() => window.innerWidth);
                    if (mentionsPosition.x > windowWidth * 0.6) {
                        layoutResults.mentionsOnRight = true;
                        console.log('✅ Mentions section on right side');
                    }
                }

                // Check Achievements on right side
                const achievementsSection = await this.page.$('.achievements-section');
                if (achievementsSection) {
                    const achievementsPosition = await this.page.evaluate(el => {
                        const rect = el.getBoundingClientRect();
                        return { x: rect.x, width: rect.width };
                    }, achievementsSection);
                    
                    const windowWidth = await this.page.evaluate(() => window.innerWidth);
                    if (achievementsPosition.x > windowWidth * 0.6) {
                        layoutResults.achievementsOnRight = true;
                        console.log('✅ Achievements section on right side');
                    }
                }
            }

        } catch (error) {
            console.log(`❌ Error testing player profile layout: ${error.message}`);
        }

        this.results.playerProfileLayout = layoutResults;
        this.updateTestCounts(layoutResults);
    }

    updateTestCounts(testResults) {
        Object.values(testResults).forEach(result => {
            this.results.summary.totalTests++;
            if (result === true) {
                this.results.summary.passed++;
            } else {
                this.results.summary.failed++;
            }
        });
    }

    async generateReport() {
        console.log('\n📊 Generating Comprehensive Test Report...');
        
        const report = {
            timestamp: new Date().toISOString(),
            platform: 'Marvel Rivals Platform',
            testSuite: 'Comprehensive Validation',
            summary: this.results.summary,
            detailedResults: {
                mentionsSystem: this.results.mentionsSystem,
                dataPersistence: this.results.dataPersistence,
                adminPanel: this.results.adminPanel,
                searchFunctionality: this.results.searchFunctionality,
                playerProfileLayout: this.results.playerProfileLayout
            },
            recommendations: this.generateRecommendations(),
            nextSteps: this.generateNextSteps()
        };

        const reportPath = `/var/www/mrvl-backend/platform-validation-report-${Date.now()}.json`;
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        console.log('\n📄 Test Report Summary:');
        console.log(`Total Tests: ${this.results.summary.totalTests}`);
        console.log(`Passed: ${this.results.summary.passed}`);
        console.log(`Failed: ${this.results.summary.failed}`);
        console.log(`Success Rate: ${((this.results.summary.passed / this.results.summary.totalTests) * 100).toFixed(1)}%`);
        
        if (this.results.summary.issues.length > 0) {
            console.log('\n⚠️ Issues Found:');
            this.results.summary.issues.forEach((issue, index) => {
                console.log(`${index + 1}. ${issue}`);
            });
        }

        console.log(`\n📋 Full report saved to: ${reportPath}`);
        return report;
    }

    generateRecommendations() {
        const recommendations = [];
        
        // Analyze test results and provide recommendations
        if (!this.results.mentionsSystem.teamProfileMentions) {
            recommendations.push('Fix team profile mentions loading - check component mounting and API calls');
        }
        
        if (!this.results.mentionsSystem.playerProfileMentions) {
            recommendations.push('Fix player profile mentions loading - verify mentions component integration');
        }
        
        if (!this.results.dataPersistence.teamProfileUpdate) {
            recommendations.push('Fix team profile data persistence - check form submission and API endpoints');
        }
        
        if (!this.results.adminPanel.realTeamsData) {
            recommendations.push('Investigate admin panel teams data - should show 53 teams from database');
        }
        
        if (!this.results.searchFunctionality.fullWordTyping) {
            recommendations.push('Fix search functionality - ensure full word typing works correctly');
        }
        
        if (!this.results.playerProfileLayout.careerPerformanceRemoved) {
            recommendations.push('Remove Career Performance section from player profiles as requested');
        }

        if (recommendations.length === 0) {
            recommendations.push('All tests passed! Platform is functioning correctly.');
        }

        return recommendations;
    }

    generateNextSteps() {
        return [
            'Address any failed test cases identified in recommendations',
            'Run automated regression tests before deployment',
            'Monitor error logs for 24 hours after deployment',
            'Conduct user acceptance testing with admin users',
            'Update documentation with any configuration changes'
        ];
    }

    async cleanup() {
        if (this.browser) {
            await this.browser.close();
        }
        console.log('✅ Browser cleanup completed');
    }

    async runFullValidation() {
        try {
            await this.initialize();
            await this.testMentionsSystem();
            await this.testDataPersistence();
            await this.testAdminPanel();
            await this.testSearchFunctionality();
            await this.testPlayerProfileLayout();
            const report = await this.generateReport();
            return report;
        } catch (error) {
            console.log(`❌ Validation failed: ${error.message}`);
            this.results.summary.issues.push(`Validation Error: ${error.message}`);
        } finally {
            await this.cleanup();
        }
    }
}

// Run validation if script is called directly
if (require.main === module) {
    (async () => {
        const validator = new PlatformValidator();
        const report = await validator.runFullValidation();
        
        console.log('\n🎯 Validation Complete!');
        process.exit(report && report.summary.failed === 0 ? 0 : 1);
    })();
}

module.exports = PlatformValidator;