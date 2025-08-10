#!/usr/bin/env node

/**
 * Test Script: Immediate Live Updates System
 * 
 * This script tests the new immediate API save functionality in SimplifiedLiveScoring
 * and the simple polling system in MatchDetailPage.
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class ImmediateLiveUpdatesTest {
  constructor() {
    this.browser = null;
    this.page = null;
    this.testResults = [];
  }

  async init() {
    this.browser = await puppeteer.launch({
      headless: false,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    this.page = await this.browser.newPage();
    
    // Set viewport and enable console logging
    await this.page.setViewport({ width: 1200, height: 800 });
    this.page.on('console', msg => {
      if (msg.text().includes('IMMEDIATE API CALL') || 
          msg.text().includes('Saving...') || 
          msg.text().includes('✅ Match data refreshed') ||
          msg.text().includes('🔄 Starting live polling')) {
        console.log('🎯 Frontend Log:', msg.text());
      }
    });
  }

  async testImmediateSaveInLiveScoring() {
    console.log('\n🔬 Testing Immediate API Saves in SimplifiedLiveScoring...');
    
    try {
      // Navigate to admin dashboard
      await this.page.goto('http://localhost:3000/admin', { waitUntil: 'networkidle2' });
      
      // Log in as admin (assuming we have test credentials)
      const loginButton = await this.page.$('button[type="submit"]');
      if (loginButton) {
        await this.page.type('input[type="email"]', 'admin@test.com');
        await this.page.type('input[type="password"]', 'password');
        await loginButton.click();
        await this.page.waitForNavigation({ waitUntil: 'networkidle2' });
      }
      
      // Look for a match to edit
      const matchButton = await this.page.$('[data-testid="match-live-scoring"], .live-scoring-button, button:contains("Live Scoring")');
      
      if (matchButton) {
        await matchButton.click();
        await this.page.waitForTimeout(2000);
        
        // Test immediate score updates
        const scoreInput = await this.page.$('input[type="number"]');
        if (scoreInput) {
          console.log('✅ Found score input, testing immediate save...');
          
          // Monitor network requests for immediate API calls
          let apiCallDetected = false;
          this.page.on('request', request => {
            if (request.url().includes('update-live-stats')) {
              console.log('🚀 IMMEDIATE API CALL DETECTED:', request.url());
              apiCallDetected = true;
            }
          });
          
          // Change score value
          await scoreInput.focus();
          await scoreInput.type('5');
          
          // Wait briefly to see if API call happens immediately
          await this.page.waitForTimeout(1000);
          
          this.testResults.push({
            test: 'Immediate Score Save',
            passed: apiCallDetected,
            details: apiCallDetected ? 'API call triggered immediately on input change' : 'No immediate API call detected'
          });
        } else {
          this.testResults.push({
            test: 'Immediate Score Save',
            passed: false,
            details: 'Could not find score input element'
          });
        }
      } else {
        this.testResults.push({
          test: 'Live Scoring Access',
          passed: false,
          details: 'Could not find Live Scoring button'
        });
      }
    } catch (error) {
      console.error('❌ Error testing immediate saves:', error);
      this.testResults.push({
        test: 'Immediate Save System',
        passed: false,
        details: `Error: ${error.message}`
      });
    }
  }

  async testPollingInMatchDetail() {
    console.log('\n🔬 Testing Simple Polling in MatchDetailPage...');
    
    try {
      // Navigate to a live match
      await this.page.goto('http://localhost:3000/match/1', { waitUntil: 'networkidle2' });
      
      // Monitor console for polling messages
      let pollingStarted = false;
      let pollingRefresh = false;
      
      this.page.on('console', msg => {
        if (msg.text().includes('🔄 Starting live polling')) {
          pollingStarted = true;
          console.log('✅ Polling started detected');
        }
        if (msg.text().includes('✅ Match data refreshed via polling')) {
          pollingRefresh = true;
          console.log('✅ Polling refresh detected');
        }
      });
      
      // Wait to see if polling starts
      await this.page.waitForTimeout(5000);
      
      // Check for live status indicator
      const liveIndicator = await this.page.$('.animate-pulse');
      const hasLiveIndicator = !!liveIndicator;
      
      this.testResults.push({
        test: 'Live Polling Start',
        passed: pollingStarted || hasLiveIndicator,
        details: pollingStarted ? 'Polling started successfully' : 
                hasLiveIndicator ? 'Live indicator present' : 'No polling detected'
      });
      
      // Wait longer to see if refresh happens
      await this.page.waitForTimeout(3000);
      
      this.testResults.push({
        test: 'Live Polling Refresh',
        passed: pollingRefresh,
        details: pollingRefresh ? 'Polling refresh detected' : 'No polling refresh in 8 seconds'
      });
      
    } catch (error) {
      console.error('❌ Error testing polling:', error);
      this.testResults.push({
        test: 'Polling System',
        passed: false,
        details: `Error: ${error.message}`
      });
    }
  }

  async testNoDelaysOrDebouncing() {
    console.log('\n🔬 Testing No Delays/Debouncing...');
    
    try {
      // This test ensures changes happen immediately without any artificial delays
      await this.page.goto('http://localhost:3000/admin', { waitUntil: 'networkidle2' });
      
      // Look for live scoring interface
      const liveButton = await this.page.$('button:contains("Live Scoring"), .live-scoring-button');
      if (liveButton) {
        await liveButton.click();
        await this.page.waitForTimeout(1000);
        
        // Monitor timing of API calls
        const apiCallTimes = [];
        this.page.on('request', request => {
          if (request.url().includes('update-live-stats')) {
            apiCallTimes.push(Date.now());
          }
        });
        
        // Make multiple rapid changes
        const inputs = await this.page.$$('input[type="number"]');
        if (inputs.length >= 2) {
          await inputs[0].focus();
          await inputs[0].type('1');
          const time1 = Date.now();
          
          await this.page.waitForTimeout(100);
          
          await inputs[1].focus();
          await inputs[1].type('2');
          const time2 = Date.now();
          
          await this.page.waitForTimeout(2000); // Wait for API calls
          
          const rapidChanges = apiCallTimes.length >= 2;
          const timingOk = apiCallTimes.length > 0 && (apiCallTimes[0] - time1) < 500;
          
          this.testResults.push({
            test: 'No Debouncing Delays',
            passed: rapidChanges && timingOk,
            details: `API calls: ${apiCallTimes.length}, Timing: ${timingOk ? 'Immediate' : 'Delayed'}`
          });
        }
      }
    } catch (error) {
      this.testResults.push({
        test: 'No Delays Test',
        passed: false,
        details: `Error: ${error.message}`
      });
    }
  }

  async generateReport() {
    const report = {
      timestamp: new Date().toISOString(),
      summary: {
        total: this.testResults.length,
        passed: this.testResults.filter(r => r.passed).length,
        failed: this.testResults.filter(r => !r.passed).length
      },
      tests: this.testResults,
      implementation_notes: [
        "✅ Removed all debouncing timeouts from SimplifiedLiveScoring.js",
        "✅ Implemented immediate API calls on every input change",
        "✅ Added simple setInterval polling (2 seconds) for live matches",
        "✅ Visual indicators show when live polling is active",
        "⚡ System now prioritizes SPEED and SIMPLICITY over complex real-time solutions"
      ]
    };

    console.log('\n📊 IMMEDIATE LIVE UPDATES TEST REPORT');
    console.log('=====================================');
    console.log(`✅ Tests Passed: ${report.summary.passed}`);
    console.log(`❌ Tests Failed: ${report.summary.failed}`);
    console.log(`📝 Total Tests: ${report.summary.total}`);
    
    console.log('\n📋 Test Details:');
    report.tests.forEach((test, index) => {
      const status = test.passed ? '✅' : '❌';
      console.log(`${index + 1}. ${status} ${test.test}: ${test.details}`);
    });

    console.log('\n🔧 Implementation Notes:');
    report.implementation_notes.forEach(note => console.log(note));

    // Save report
    const reportPath = path.join(__dirname, 'immediate-live-updates-test-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`\n📄 Full report saved: ${reportPath}`);

    return report;
  }

  async cleanup() {
    if (this.browser) {
      await this.browser.close();
    }
  }

  async run() {
    console.log('🚀 Starting Immediate Live Updates Test Suite...');
    console.log('Testing SIMPLE and STRONG immediate updates without complex real-time systems');
    
    try {
      await this.init();
      
      // Run all tests
      await this.testImmediateSaveInLiveScoring();
      await this.testPollingInMatchDetail();
      await this.testNoDelaysOrDebouncing();
      
      // Generate final report
      const report = await this.generateReport();
      
      return report;
    } catch (error) {
      console.error('❌ Test suite error:', error);
      throw error;
    } finally {
      await this.cleanup();
    }
  }
}

// Run the test if this file is executed directly
if (require.main === module) {
  const tester = new ImmediateLiveUpdatesTest();
  tester.run()
    .then(() => {
      console.log('✅ Test suite completed successfully');
      process.exit(0);
    })
    .catch(error => {
      console.error('❌ Test suite failed:', error);
      process.exit(1);
    });
}

module.exports = ImmediateLiveUpdatesTest;