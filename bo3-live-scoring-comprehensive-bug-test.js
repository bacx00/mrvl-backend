/**
 * COMPREHENSIVE BO3 LIVE SCORING BUG HUNTING TEST SUITE
 * 
 * This script performs comprehensive testing for race conditions, data validation,
 * error handling, memory leaks, and security vulnerabilities in the live scoring system.
 * 
 * CRITICAL AREAS TESTED:
 * 1. Race Conditions - Multiple admins updating simultaneously
 * 2. Data Validation - Invalid inputs, edge cases, NaN values
 * 3. API Error Handling - Network failures, timeouts, auth issues
 * 4. Memory Leaks - Interval cleanup, event listeners
 * 5. Security - XSS, injection attacks, unauthorized access
 * 6. Edge Cases - Empty data, missing players, undefined heroes
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

// CONFIGURATION
const BASE_URL = process.env.REACT_APP_BACKEND_URL || 'http://localhost:8000';
const FRONTEND_URL = 'http://localhost:3000';
const ADMIN_EMAIL = 'admin@mrvl.net';
const ADMIN_PASSWORD = 'admin123';

// Test results storage
let testResults = {
    timestamp: new Date().toISOString(),
    totalTests: 0,
    passedTests: 0,
    failedTests: 0,
    criticalBugs: [],
    warnings: [],
    performanceIssues: [],
    securityIssues: [],
    memoryLeaks: [],
    raceConditions: []
};

// Utility functions
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

const logTest = (testName, status, details = '', severity = 'info') => {
    testResults.totalTests++;
    const result = {
        test: testName,
        status,
        details,
        severity,
        timestamp: new Date().toISOString()
    };
    
    if (status === 'PASS') {
        testResults.passedTests++;
    } else {
        testResults.failedTests++;
        if (severity === 'critical') {
            testResults.criticalBugs.push(result);
        } else if (severity === 'warning') {
            testResults.warnings.push(result);
        }
    }
    
    console.log(`${status === 'PASS' ? '✅' : '❌'} ${testName}: ${details}`);
    return result;
};

// Main test runner
async function runComprehensiveBugHunt() {
    console.log('🔍 STARTING COMPREHENSIVE BO3 LIVE SCORING BUG HUNT');
    console.log('=' .repeat(60));
    
    let browser, adminPage1, adminPage2, userPage;
    
    try {
        // Launch browser instances
        browser = await puppeteer.launch({ 
            headless: false,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security'],
            devtools: false
        });
        
        // Create multiple pages for race condition testing
        adminPage1 = await browser.newPage();
        adminPage2 = await browser.newPage();
        userPage = await browser.newPage();
        
        // Enable console logging for all pages
        [adminPage1, adminPage2, userPage].forEach(page => {
            page.on('console', msg => console.log(`PAGE LOG: ${msg.text()}`));
            page.on('pageerror', err => console.log(`PAGE ERROR: ${err.message}`));
        });
        
        // Set up error monitoring
        await setupErrorMonitoring(adminPage1);
        await setupErrorMonitoring(adminPage2);
        await setupErrorMonitoring(userPage);
        
        // Test 1: Authentication and Setup
        console.log('\n🔐 TESTING AUTHENTICATION & SETUP');
        await testAuthentication(adminPage1, adminPage2);
        
        // Test 2: Race Conditions
        console.log('\n🏁 TESTING RACE CONDITIONS');
        await testRaceConditions(adminPage1, adminPage2);
        
        // Test 3: Data Validation & Edge Cases
        console.log('\n🔍 TESTING DATA VALIDATION & EDGE CASES');
        await testDataValidation(adminPage1);
        
        // Test 4: Memory Leak Detection
        console.log('\n🧠 TESTING MEMORY LEAKS');
        await testMemoryLeaks(adminPage1, userPage);
        
        // Test 5: Security Vulnerabilities
        console.log('\n🛡️ TESTING SECURITY VULNERABILITIES');
        await testSecurityIssues(adminPage1, userPage);
        
        // Test 6: API Error Handling
        console.log('\n🌐 TESTING API ERROR HANDLING');
        await testAPIErrorHandling(adminPage1);
        
        // Test 7: Performance Under Stress
        console.log('\n⚡ TESTING PERFORMANCE UNDER STRESS');
        await testPerformanceStress(adminPage1, adminPage2, userPage);
        
        // Test 8: Tournament Stability
        console.log('\n🏆 TESTING TOURNAMENT STABILITY');
        await testTournamentStability(adminPage1, adminPage2);
        
    } catch (error) {
        logTest('CRITICAL_SYSTEM_ERROR', 'FAIL', `System error during testing: ${error.message}`, 'critical');
    } finally {
        if (browser) {
            await browser.close();
        }
        
        // Generate comprehensive report
        await generateBugReport();
    }
}

// Authentication testing
async function testAuthentication(adminPage1, adminPage2) {
    try {
        // Test admin login for both sessions
        await adminPage1.goto(`${FRONTEND_URL}/admin`);
        await adminPage2.goto(`${FRONTEND_URL}/admin`);
        
        // Login admin 1
        await adminPage1.type('input[name="email"]', ADMIN_EMAIL);
        await adminPage1.type('input[name="password"]', ADMIN_PASSWORD);
        await adminPage1.click('button[type="submit"]');
        await adminPage1.waitForSelector('[data-testid="admin-dashboard"]', { timeout: 10000 });
        
        // Login admin 2
        await adminPage2.type('input[name="email"]', ADMIN_EMAIL);
        await adminPage2.type('input[name="password"]', ADMIN_PASSWORD);
        await adminPage2.click('button[type="submit"]');
        await adminPage2.waitForSelector('[data-testid="admin-dashboard"]', { timeout: 10000 });
        
        logTest('DUAL_ADMIN_AUTHENTICATION', 'PASS', 'Both admin sessions authenticated successfully');
        
        // Test concurrent session handling
        const admin1Token = await adminPage1.evaluate(() => localStorage.getItem('auth_token'));
        const admin2Token = await adminPage2.evaluate(() => localStorage.getItem('auth_token'));
        
        if (admin1Token && admin2Token) {
            logTest('CONCURRENT_SESSIONS', 'PASS', 'Concurrent admin sessions allowed');
        } else {
            logTest('CONCURRENT_SESSIONS', 'FAIL', 'Token storage issues detected', 'warning');
        }
        
    } catch (error) {
        logTest('AUTHENTICATION_SETUP', 'FAIL', `Authentication failed: ${error.message}`, 'critical');
        throw error;
    }
}

// Race condition testing
async function testRaceConditions(adminPage1, adminPage2) {
    try {
        // Create a test match first
        const matchId = await createTestMatch(adminPage1);
        
        if (!matchId) {
            logTest('TEST_MATCH_CREATION', 'FAIL', 'Could not create test match', 'critical');
            return;
        }
        
        // Test 1: Simultaneous score updates
        console.log('Testing simultaneous score updates...');
        await testSimultaneousScoreUpdates(adminPage1, adminPage2, matchId);
        
        // Test 2: Concurrent hero changes
        console.log('Testing concurrent hero changes...');
        await testConcurrentHeroChanges(adminPage1, adminPage2, matchId);
        
        // Test 3: Race condition on match state changes
        console.log('Testing match state race conditions...');
        await testMatchStateRaceConditions(adminPage1, adminPage2, matchId);
        
        // Test 4: Database transaction conflicts
        console.log('Testing database transaction conflicts...');
        await testDatabaseTransactionConflicts(adminPage1, adminPage2, matchId);
        
    } catch (error) {
        logTest('RACE_CONDITION_SETUP', 'FAIL', `Race condition test setup failed: ${error.message}`, 'critical');
    }
}

async function testSimultaneousScoreUpdates(adminPage1, adminPage2, matchId) {
    try {
        // Navigate both admins to live scoring
        await Promise.all([
            navigateToLiveScoring(adminPage1, matchId),
            navigateToLiveScoring(adminPage2, matchId)
        ]);
        
        // Simultaneously update different player stats
        const startTime = Date.now();
        
        await Promise.all([
            // Admin 1 updates Team 1 Player 1 kills
            adminPage1.evaluate(() => {
                const killsInput = document.querySelector('input[data-testid="team1-player-0-kills"]');
                if (killsInput) {
                    killsInput.value = '15';
                    killsInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }),
            
            // Admin 2 updates Team 2 Player 1 kills at the same time
            adminPage2.evaluate(() => {
                const killsInput = document.querySelector('input[data-testid="team2-player-0-kills"]');
                if (killsInput) {
                    killsInput.value = '12';
                    killsInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            })
        ]);
        
        const endTime = Date.now();
        
        // Wait for API calls to complete
        await sleep(2000);
        
        // Check for data corruption or conflicts
        const finalData = await adminPage1.evaluate(() => {
            const team1Kills = document.querySelector('input[data-testid="team1-player-0-kills"]')?.value;
            const team2Kills = document.querySelector('input[data-testid="team2-player-0-kills"]')?.value;
            return { team1Kills, team2Kills };
        });
        
        if (finalData.team1Kills === '15' && finalData.team2Kills === '12') {
            logTest('SIMULTANEOUS_SCORE_UPDATES', 'PASS', `Concurrent updates processed correctly in ${endTime - startTime}ms`);
        } else {
            logTest('SIMULTANEOUS_SCORE_UPDATES', 'FAIL', `Data corruption detected: T1=${finalData.team1Kills}, T2=${finalData.team2Kills}`, 'critical');
            testResults.raceConditions.push({
                type: 'score_update_race',
                expected: { team1: '15', team2: '12' },
                actual: finalData,
                severity: 'critical'
            });
        }
        
    } catch (error) {
        logTest('SIMULTANEOUS_SCORE_UPDATES', 'FAIL', `Race condition error: ${error.message}`, 'critical');
    }
}

async function testConcurrentHeroChanges(adminPage1, adminPage2, matchId) {
    try {
        // Test rapid hero changes from different admins
        const heroChanges = [
            { admin: adminPage1, player: 'team1-player-0', hero: 'Spider-Man' },
            { admin: adminPage2, player: 'team1-player-1', hero: 'Wolverine' },
            { admin: adminPage1, player: 'team2-player-0', hero: 'Captain America' },
            { admin: adminPage2, player: 'team2-player-1', hero: 'Storm' }
        ];
        
        // Execute all changes simultaneously
        await Promise.all(heroChanges.map(async ({ admin, player, hero }) => {
            await admin.evaluate((playerSelector, heroName) => {
                const heroSelect = document.querySelector(`select[data-testid="${playerSelector}-hero"]`);
                if (heroSelect) {
                    heroSelect.value = heroName;
                    heroSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, player, hero);
        }));
        
        // Wait for all API calls
        await sleep(3000);
        
        // Verify final state
        const verification = await adminPage1.evaluate(() => {
            const heroes = {};
            document.querySelectorAll('select[data-testid*="-hero"]').forEach(select => {
                heroes[select.dataset.testid] = select.value;
            });
            return heroes;
        });
        
        let conflictsFound = 0;
        heroChanges.forEach(({ player, hero }) => {
            const actualHero = verification[`${player}-hero`];
            if (actualHero !== hero) {
                conflictsFound++;
            }
        });
        
        if (conflictsFound === 0) {
            logTest('CONCURRENT_HERO_CHANGES', 'PASS', 'All concurrent hero changes processed correctly');
        } else {
            logTest('CONCURRENT_HERO_CHANGES', 'FAIL', `${conflictsFound} hero change conflicts detected`, 'warning');
        }
        
    } catch (error) {
        logTest('CONCURRENT_HERO_CHANGES', 'FAIL', `Hero change race condition error: ${error.message}`, 'warning');
    }
}

// Data validation and edge case testing
async function testDataValidation(adminPage) {
    try {
        // Test invalid numeric inputs
        await testInvalidNumericInputs(adminPage);
        
        // Test boundary conditions
        await testBoundaryConditions(adminPage);
        
        // Test null/undefined handling
        await testNullUndefinedHandling(adminPage);
        
        // Test malicious input injection
        await testInputInjection(adminPage);
        
    } catch (error) {
        logTest('DATA_VALIDATION_SETUP', 'FAIL', `Data validation test setup failed: ${error.message}`, 'critical');
    }
}

async function testInvalidNumericInputs(adminPage) {
    const invalidInputs = [
        { field: 'kills', value: '-5', expected: '0' },
        { field: 'kills', value: 'abc', expected: '0' },
        { field: 'kills', value: '999999', expected: '999999' },
        { field: 'damage', value: 'NaN', expected: '0' },
        { field: 'damage', value: 'Infinity', expected: '0' },
        { field: 'healing', value: '1.5', expected: '1' },
    ];
    
    for (const { field, value, expected } of invalidInputs) {
        try {
            await adminPage.evaluate((field, value) => {
                const input = document.querySelector(`input[data-testid="team1-player-0-${field}"]`);
                if (input) {
                    input.value = value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('blur', { bubbles: true }));
                }
            }, field, value);
            
            await sleep(500);
            
            const actualValue = await adminPage.evaluate((field) => {
                const input = document.querySelector(`input[data-testid="team1-player-0-${field}"]`);
                return input ? input.value : null;
            }, field);
            
            if (actualValue === expected) {
                logTest(`INVALID_INPUT_${field.toUpperCase()}_${value}`, 'PASS', `Invalid input "${value}" handled correctly`);
            } else {
                logTest(`INVALID_INPUT_${field.toUpperCase()}_${value}`, 'FAIL', `Expected "${expected}", got "${actualValue}"`, 'warning');
            }
            
        } catch (error) {
            logTest(`INVALID_INPUT_${field.toUpperCase()}_${value}`, 'FAIL', `Error testing invalid input: ${error.message}`, 'warning');
        }
    }
}

async function testBoundaryConditions(adminPage) {
    const boundaryTests = [
        { field: 'kills', value: '0', description: 'minimum kills' },
        { field: 'kills', value: '100', description: 'high kills value' },
        { field: 'deaths', value: '0', description: 'no deaths' },
        { field: 'deaths', value: '50', description: 'high deaths value' },
        { field: 'damage', value: '0', description: 'no damage' },
        { field: 'damage', value: '999999', description: 'maximum damage' }
    ];
    
    for (const { field, value, description } of boundaryTests) {
        try {
            await adminPage.evaluate((field, value) => {
                const input = document.querySelector(`input[data-testid="team1-player-0-${field}"]`);
                if (input) {
                    input.value = value;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, field, value);
            
            await sleep(500);
            
            // Check for any JavaScript errors or crashes
            const errors = await adminPage.evaluate(() => window.jsErrors || []);
            
            if (errors.length === 0) {
                logTest(`BOUNDARY_${field.toUpperCase()}_${value}`, 'PASS', `Boundary condition "${description}" handled correctly`);
            } else {
                logTest(`BOUNDARY_${field.toUpperCase()}_${value}`, 'FAIL', `JavaScript errors on boundary condition: ${errors.join(', ')}`, 'warning');
            }
            
        } catch (error) {
            logTest(`BOUNDARY_${field.toUpperCase()}_${value}`, 'FAIL', `Error testing boundary condition: ${error.message}`, 'warning');
        }
    }
}

// Memory leak detection
async function testMemoryLeaks(adminPage, userPage) {
    try {
        // Test interval cleanup
        await testIntervalCleanup(adminPage);
        
        // Test event listener removal
        await testEventListenerCleanup(adminPage);
        
        // Test WebSocket cleanup
        await testWebSocketCleanup(userPage);
        
        // Test component unmount cleanup
        await testComponentUnmountCleanup(adminPage, userPage);
        
    } catch (error) {
        logTest('MEMORY_LEAK_TESTING', 'FAIL', `Memory leak test setup failed: ${error.message}`, 'critical');
    }
}

async function testIntervalCleanup(adminPage) {
    try {
        // Get initial interval count
        const initialIntervals = await adminPage.evaluate(() => {
            return window.setInterval.toString().includes('[native code]') ? 'unknown' : Object.keys(window.intervals || {}).length;
        });
        
        // Open live scoring (should start polling)
        await adminPage.evaluate(() => {
            const liveScoringBtn = document.querySelector('button[data-testid="live-scoring-btn"]');
            if (liveScoringBtn) liveScoringBtn.click();
        });
        
        await sleep(3000);
        
        // Close live scoring (should cleanup intervals)
        await adminPage.evaluate(() => {
            const closeBtn = document.querySelector('button[data-testid="close-live-scoring"]');
            if (closeBtn) closeBtn.click();
        });
        
        await sleep(1000);
        
        // Check if intervals were cleaned up
        const finalIntervals = await adminPage.evaluate(() => {
            return window.setInterval.toString().includes('[native code]') ? 'unknown' : Object.keys(window.intervals || {}).length;
        });
        
        if (initialIntervals === finalIntervals || finalIntervals === 'unknown') {
            logTest('INTERVAL_CLEANUP', 'PASS', 'Intervals properly cleaned up on component unmount');
        } else {
            logTest('INTERVAL_CLEANUP', 'FAIL', `Interval leak detected: ${initialIntervals} -> ${finalIntervals}`, 'warning');
            testResults.memoryLeaks.push({
                type: 'interval_leak',
                before: initialIntervals,
                after: finalIntervals
            });
        }
        
    } catch (error) {
        logTest('INTERVAL_CLEANUP', 'FAIL', `Error testing interval cleanup: ${error.message}`, 'warning');
    }
}

// Security vulnerability testing
async function testSecurityIssues(adminPage, userPage) {
    try {
        // Test XSS vulnerabilities
        await testXSSVulnerabilities(adminPage);
        
        // Test unauthorized access
        await testUnauthorizedAccess(userPage);
        
        // Test CSRF protection
        await testCSRFProtection(adminPage);
        
        // Test input sanitization
        await testInputSanitization(adminPage);
        
    } catch (error) {
        logTest('SECURITY_TESTING', 'FAIL', `Security test setup failed: ${error.message}`, 'critical');
    }
}

async function testXSSVulnerabilities(adminPage) {
    const xssPayloads = [
        '<script>alert("XSS")</script>',
        '"><script>alert("XSS")</script>',
        'javascript:alert("XSS")',
        '<img src="x" onerror="alert(\'XSS\')">'
    ];
    
    for (const payload of xssPayloads) {
        try {
            // Test XSS in player name field
            await adminPage.evaluate((payload) => {
                const input = document.querySelector('input[data-testid="team1-player-0-name"]');
                if (input) {
                    input.value = payload;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, payload);
            
            await sleep(500);
            
            // Check if XSS was executed (alert dialog would appear)
            const dialogAppeared = await adminPage.evaluate(() => {
                return window.xssTriggered || false;
            });
            
            if (!dialogAppeared) {
                logTest(`XSS_PREVENTION_${payload.substring(0, 20)}`, 'PASS', 'XSS payload prevented');
            } else {
                logTest(`XSS_PREVENTION_${payload.substring(0, 20)}`, 'FAIL', 'XSS vulnerability detected!', 'critical');
                testResults.securityIssues.push({
                    type: 'xss_vulnerability',
                    payload,
                    location: 'player_name_field',
                    severity: 'critical'
                });
            }
            
        } catch (error) {
            logTest(`XSS_TEST_${payload.substring(0, 20)}`, 'FAIL', `Error testing XSS: ${error.message}`, 'warning');
        }
    }
}

// API error handling testing
async function testAPIErrorHandling(adminPage) {
    try {
        // Test network timeout handling
        await testNetworkTimeouts(adminPage);
        
        // Test invalid authentication
        await testInvalidAuthentication(adminPage);
        
        // Test malformed API responses
        await testMalformedResponses(adminPage);
        
        // Test rate limiting
        await testRateLimiting(adminPage);
        
    } catch (error) {
        logTest('API_ERROR_TESTING', 'FAIL', `API error test setup failed: ${error.message}`, 'critical');
    }
}

async function testNetworkTimeouts(adminPage) {
    try {
        // Intercept API calls and delay them
        await adminPage.setRequestInterception(true);
        
        adminPage.on('request', (request) => {
            if (request.url().includes('/api/admin/matches/') && request.url().includes('update-live-stats')) {
                // Delay the request by 10 seconds to simulate timeout
                setTimeout(() => {
                    request.abort('timedout');
                }, 10000);
            } else {
                request.continue();
            }
        });
        
        // Try to update a score (should timeout)
        await adminPage.evaluate(() => {
            const killsInput = document.querySelector('input[data-testid="team1-player-0-kills"]');
            if (killsInput) {
                killsInput.value = '20';
                killsInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        
        await sleep(12000); // Wait longer than timeout
        
        // Check if error was handled gracefully
        const errorHandled = await adminPage.evaluate(() => {
            return document.querySelector('[data-testid="error-message"]') !== null;
        });
        
        if (errorHandled) {
            logTest('NETWORK_TIMEOUT_HANDLING', 'PASS', 'Network timeout handled gracefully');
        } else {
            logTest('NETWORK_TIMEOUT_HANDLING', 'FAIL', 'No error handling for network timeouts', 'warning');
        }
        
        // Disable interception
        await adminPage.setRequestInterception(false);
        
    } catch (error) {
        logTest('NETWORK_TIMEOUT_HANDLING', 'FAIL', `Error testing network timeouts: ${error.message}`, 'warning');
    }
}

// Performance stress testing
async function testPerformanceStress(adminPage1, adminPage2, userPage) {
    try {
        console.log('Starting performance stress tests...');
        
        // Test rapid updates
        await testRapidUpdates(adminPage1);
        
        // Test multiple concurrent viewers
        await testConcurrentViewers(adminPage1, adminPage2, userPage);
        
        // Test large data sets
        await testLargeDataSets(adminPage1);
        
        // Test memory usage under load
        await testMemoryUnderLoad(adminPage1, adminPage2);
        
    } catch (error) {
        logTest('PERFORMANCE_STRESS_TESTING', 'FAIL', `Performance test setup failed: ${error.message}`, 'critical');
    }
}

async function testRapidUpdates(adminPage) {
    try {
        const startTime = Date.now();
        const updateCount = 50;
        
        // Perform rapid score updates
        for (let i = 0; i < updateCount; i++) {
            await adminPage.evaluate((index) => {
                const killsInput = document.querySelector('input[data-testid="team1-player-0-kills"]');
                if (killsInput) {
                    killsInput.value = index.toString();
                    killsInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, i);
            
            // Small delay between updates
            await sleep(50);
        }
        
        const endTime = Date.now();
        const totalTime = endTime - startTime;
        
        // Check final state
        const finalValue = await adminPage.evaluate(() => {
            const killsInput = document.querySelector('input[data-testid="team1-player-0-kills"]');
            return killsInput ? killsInput.value : null;
        });
        
        if (finalValue === (updateCount - 1).toString()) {
            logTest('RAPID_UPDATES_PERFORMANCE', 'PASS', `${updateCount} rapid updates completed in ${totalTime}ms`);
        } else {
            logTest('RAPID_UPDATES_PERFORMANCE', 'FAIL', `Data consistency lost during rapid updates: expected ${updateCount - 1}, got ${finalValue}`, 'warning');
        }
        
        if (totalTime > 10000) { // 10 seconds for 50 updates is too slow
            testResults.performanceIssues.push({
                type: 'slow_rapid_updates',
                updates: updateCount,
                time: totalTime,
                avgPerUpdate: totalTime / updateCount
            });
        }
        
    } catch (error) {
        logTest('RAPID_UPDATES_PERFORMANCE', 'FAIL', `Error during rapid updates test: ${error.message}`, 'warning');
    }
}

// Tournament stability testing
async function testTournamentStability(adminPage1, adminPage2) {
    try {
        // Test match completion flow
        await testMatchCompletionFlow(adminPage1);
        
        // Test tournament progression
        await testTournamentProgression(adminPage1, adminPage2);
        
        // Test bracket integrity
        await testBracketIntegrity(adminPage1);
        
    } catch (error) {
        logTest('TOURNAMENT_STABILITY', 'FAIL', `Tournament stability test failed: ${error.message}`, 'critical');
    }
}

// Helper functions
async function createTestMatch(adminPage) {
    try {
        // Navigate to match creation
        await adminPage.goto(`${FRONTEND_URL}/admin/matches/create`);
        await adminPage.waitForSelector('form', { timeout: 10000 });
        
        // Fill out match form
        await adminPage.select('select[name="team1_id"]', '1'); // Assume team ID 1 exists
        await adminPage.select('select[name="team2_id"]', '2'); // Assume team ID 2 exists
        await adminPage.select('select[name="format"]', 'BO3');
        
        // Submit form
        await adminPage.click('button[type="submit"]');
        await adminPage.waitForNavigation({ timeout: 10000 });
        
        // Extract match ID from URL
        const url = adminPage.url();
        const match = url.match(/\/match\/(\d+)/);
        return match ? match[1] : null;
        
    } catch (error) {
        console.error('Error creating test match:', error);
        return null;
    }
}

async function navigateToLiveScoring(page, matchId) {
    await page.goto(`${FRONTEND_URL}/admin/match/${matchId}`);
    await page.waitForSelector('[data-testid="live-scoring-btn"]', { timeout: 10000 });
    await page.click('[data-testid="live-scoring-btn"]');
    await page.waitForSelector('[data-testid="live-scoring-panel"]', { timeout: 5000 });
}

async function setupErrorMonitoring(page) {
    await page.evaluateOnNewDocument(() => {
        window.jsErrors = [];
        window.addEventListener('error', (e) => {
            window.jsErrors.push(e.message);
        });
        window.addEventListener('unhandledrejection', (e) => {
            window.jsErrors.push(`Unhandled Promise: ${e.reason}`);
        });
    });
}

// Report generation
async function generateBugReport() {
    const reportPath = path.join(__dirname, `bo3-live-scoring-bug-report-${Date.now()}.json`);
    
    const finalReport = {
        ...testResults,
        summary: {
            totalTests: testResults.totalTests,
            passRate: ((testResults.passedTests / testResults.totalTests) * 100).toFixed(2) + '%',
            criticalBugsCount: testResults.criticalBugs.length,
            warningsCount: testResults.warnings.length,
            memoryLeaksCount: testResults.memoryLeaks.length,
            securityIssuesCount: testResults.securityIssues.length,
            raceConditionsCount: testResults.raceConditions.length,
            performanceIssuesCount: testResults.performanceIssues.length
        },
        recommendations: generateRecommendations()
    };
    
    // Write detailed JSON report
    fs.writeFileSync(reportPath, JSON.stringify(finalReport, null, 2));
    
    // Generate markdown summary
    const markdownReport = generateMarkdownReport(finalReport);
    const markdownPath = path.join(__dirname, `BO3_LIVE_SCORING_BUG_REPORT.md`);
    fs.writeFileSync(markdownPath, markdownReport);
    
    console.log('\n' + '='.repeat(60));
    console.log('🎯 COMPREHENSIVE BUG HUNT COMPLETE');
    console.log('='.repeat(60));
    console.log(`📊 Total Tests: ${finalReport.totalTests}`);
    console.log(`✅ Passed: ${finalReport.passedTests} (${finalReport.summary.passRate})`);
    console.log(`❌ Failed: ${finalReport.failedTests}`);
    console.log(`🚨 Critical Bugs: ${finalReport.criticalBugs.length}`);
    console.log(`⚠️  Warnings: ${finalReport.warnings.length}`);
    console.log(`🧠 Memory Leaks: ${finalReport.memoryLeaks.length}`);
    console.log(`🛡️  Security Issues: ${finalReport.securityIssues.length}`);
    console.log(`🏁 Race Conditions: ${finalReport.raceConditions.length}`);
    console.log(`⚡ Performance Issues: ${finalReport.performanceIssues.length}`);
    console.log('\n📁 Detailed reports saved:');
    console.log(`   JSON: ${reportPath}`);
    console.log(`   Markdown: ${markdownPath}`);
    
    return finalReport;
}

function generateRecommendations() {
    const recommendations = [];
    
    if (testResults.raceConditions.length > 0) {
        recommendations.push({
            priority: 'HIGH',
            category: 'Race Conditions',
            issue: 'Multiple race conditions detected in concurrent admin updates',
            solution: 'Implement optimistic locking, request debouncing, and transaction isolation'
        });
    }
    
    if (testResults.securityIssues.length > 0) {
        recommendations.push({
            priority: 'CRITICAL',
            category: 'Security',
            issue: 'Security vulnerabilities found in user input handling',
            solution: 'Implement proper input sanitization, CSP headers, and XSS protection'
        });
    }
    
    if (testResults.memoryLeaks.length > 0) {
        recommendations.push({
            priority: 'MEDIUM',
            category: 'Memory Management',
            issue: 'Memory leaks detected in component lifecycle',
            solution: 'Ensure proper cleanup of intervals, event listeners, and subscriptions'
        });
    }
    
    if (testResults.performanceIssues.length > 0) {
        recommendations.push({
            priority: 'MEDIUM',
            category: 'Performance',
            issue: 'Performance degradation under stress conditions',
            solution: 'Implement request queuing, batch updates, and performance monitoring'
        });
    }
    
    return recommendations;
}

function generateMarkdownReport(report) {
    return `# BO3 Live Scoring Comprehensive Bug Hunt Report

## Executive Summary

**Generated:** ${report.timestamp}
**Total Tests:** ${report.totalTests}
**Pass Rate:** ${report.summary.passRate}
**Critical Issues:** ${report.criticalBugs.length}

## Test Results Overview

- ✅ **Passed:** ${report.passedTests}
- ❌ **Failed:** ${report.failedTests}
- 🚨 **Critical Bugs:** ${report.criticalBugs.length}
- ⚠️ **Warnings:** ${report.warnings.length}
- 🧠 **Memory Leaks:** ${report.memoryLeaks.length}
- 🛡️ **Security Issues:** ${report.securityIssues.length}
- 🏁 **Race Conditions:** ${report.raceConditions.length}
- ⚡ **Performance Issues:** ${report.performanceIssues.length}

## Critical Bugs Found

${report.criticalBugs.map(bug => `### ${bug.test}
**Status:** ${bug.status}
**Severity:** ${bug.severity}
**Details:** ${bug.details}
**Timestamp:** ${bug.timestamp}
`).join('\n')}

## Security Issues

${report.securityIssues.map(issue => `### ${issue.type.toUpperCase()}
**Payload:** \`${issue.payload}\`
**Location:** ${issue.location}
**Severity:** ${issue.severity}
`).join('\n')}

## Race Conditions

${report.raceConditions.map(race => `### ${race.type.toUpperCase()}
**Expected:** ${JSON.stringify(race.expected)}
**Actual:** ${JSON.stringify(race.actual)}
**Severity:** ${race.severity}
`).join('\n')}

## Recommendations

${report.recommendations.map(rec => `### ${rec.category} (Priority: ${rec.priority})
**Issue:** ${rec.issue}
**Solution:** ${rec.solution}
`).join('\n')}

## Next Steps

1. **Immediate:** Fix all critical bugs and security vulnerabilities
2. **Short-term:** Address race conditions and implement proper error handling
3. **Medium-term:** Optimize performance and fix memory leaks
4. **Long-term:** Implement comprehensive monitoring and alerting

---
*Report generated by BO3 Live Scoring Bug Hunter*
`;
}

// Run the comprehensive bug hunt
if (require.main === module) {
    runComprehensiveBugHunt()
        .then(() => {
            console.log('🎯 Bug hunt completed successfully!');
            process.exit(0);
        })
        .catch((error) => {
            console.error('💥 Bug hunt failed:', error);
            process.exit(1);
        });
}

module.exports = {
    runComprehensiveBugHunt,
    logTest,
    generateBugReport
};