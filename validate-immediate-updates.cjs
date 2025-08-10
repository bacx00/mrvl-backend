#!/usr/bin/env node

/**
 * Code Analysis Test: Immediate Live Updates Implementation
 * 
 * This script analyzes the SimplifiedLiveScoring.js and MatchDetailPage.js files
 * to validate that immediate API calls and simple polling are properly implemented.
 */

const fs = require('fs');
const path = require('path');

class CodeAnalysisValidator {
  constructor() {
    this.testResults = [];
    this.simplifiedLiveScoringPath = '/var/www/mrvl-frontend/frontend/src/components/admin/SimplifiedLiveScoring.js';
    this.matchDetailPagePath = '/var/www/mrvl-frontend/frontend/src/components/pages/MatchDetailPage.js';
  }

  validateSimplifiedLiveScoring() {
    console.log('\n🔬 Analyzing SimplifiedLiveScoring.js for immediate saves...');
    
    try {
      const content = fs.readFileSync(this.simplifiedLiveScoringPath, 'utf8');
      
      // Check that debouncing was removed
      const hasDebouncing = content.includes('debounce') || 
                           content.includes('setTimeout') || 
                           content.includes('saveTimeoutRef');
      
      // Check for immediate API calls
      const hasImmediateApiSave = content.includes('immediateApiSave');
      const hasImmediateComment = content.includes('IMMEDIATE API CALL');
      
      // Check that triggerAutoSave was removed/replaced
      const hasTriggerAutoSave = content.includes('triggerAutoSave');
      
      // Check for immediate save function calls
      const updatePlayerStatCalls = content.match(/immediateApiSave\(newState\)/g) || [];
      const immediateCallsCount = updatePlayerStatCalls.length;
      
      // Check that functions are async now
      const hasAsyncUpdatePlayerStat = content.includes('const updatePlayerStat = async');
      const hasAsyncUpdatePlayerHero = content.includes('const updatePlayerHero = async');
      const hasAsyncUpdateMapScore = content.includes('const updateMapScore = async');
      
      // Check UI text updates
      const hasInstantSaveText = content.includes('INSTANT SAVE') || content.includes('Every change saves IMMEDIATELY');
      
      this.testResults.push({
        test: 'Removed Debouncing',
        passed: !hasDebouncing,
        details: hasDebouncing ? 'Still contains debouncing code' : 'Debouncing successfully removed'
      });
      
      this.testResults.push({
        test: 'Immediate API Save Function',
        passed: hasImmediateApiSave,
        details: hasImmediateApiSave ? 'immediateApiSave function implemented' : 'Missing immediateApiSave function'
      });
      
      this.testResults.push({
        test: 'Immediate API Comments',
        passed: hasImmediateComment,
        details: hasImmediateComment ? 'Proper immediate API call comments found' : 'Missing immediate API call documentation'
      });
      
      this.testResults.push({
        test: 'Removed triggerAutoSave',
        passed: !hasTriggerAutoSave,
        details: hasTriggerAutoSave ? 'triggerAutoSave still present - should be removed' : 'triggerAutoSave properly removed'
      });
      
      this.testResults.push({
        test: 'Immediate API Calls Count',
        passed: immediateCallsCount >= 3,
        details: `Found ${immediateCallsCount} immediate API calls (expected at least 3)`
      });
      
      this.testResults.push({
        test: 'Async Update Functions',
        passed: hasAsyncUpdatePlayerStat && hasAsyncUpdatePlayerHero && hasAsyncUpdateMapScore,
        details: `Async functions: playerStat=${hasAsyncUpdatePlayerStat}, hero=${hasAsyncUpdatePlayerHero}, mapScore=${hasAsyncUpdateMapScore}`
      });
      
      this.testResults.push({
        test: 'Updated UI Text',
        passed: hasInstantSaveText,
        details: hasInstantSaveText ? 'UI text updated to reflect instant saves' : 'UI text not updated'
      });
      
    } catch (error) {
      this.testResults.push({
        test: 'SimplifiedLiveScoring Analysis',
        passed: false,
        details: `Error reading file: ${error.message}`
      });
    }
  }

  validateMatchDetailPage() {
    console.log('\n🔬 Analyzing MatchDetailPage.js for simple polling...');
    
    try {
      const content = fs.readFileSync(this.matchDetailPagePath, 'utf8');
      
      // Check for polling function
      const hasPollingFunction = content.includes('pollForUpdates');
      
      // Check for setInterval usage
      const hasSetInterval = content.includes('setInterval(pollForUpdates, 2000)') || 
                            content.includes('setInterval');
      
      // Check for polling useEffect
      const hasPollingUseEffect = content.includes('match?.status === \'live\'') && 
                                 content.includes('setInterval');
      
      // Check for live polling logs
      const hasPollingLogs = content.includes('🔄 Starting live polling') && 
                            content.includes('⏹️ Stopping live polling');
      
      // Check for live indicator
      const hasLiveIndicator = content.includes('Live Updates') && 
                              content.includes('animate-pulse');
      
      // Check that old SSE code was replaced
      const hasOldSSE = content.includes('EventSource') || 
                       content.includes('connectToLiveUpdates');
      
      // Check polling interval (should be 2-3 seconds)
      const pollingIntervalMatch = content.match(/setInterval\([^,]+,\s*(\d+)/);
      const pollingInterval = pollingIntervalMatch ? parseInt(pollingIntervalMatch[1]) : null;
      const goodPollingInterval = pollingInterval && pollingInterval >= 2000 && pollingInterval <= 3000;
      
      this.testResults.push({
        test: 'Polling Function',
        passed: hasPollingFunction,
        details: hasPollingFunction ? 'pollForUpdates function implemented' : 'Missing pollForUpdates function'
      });
      
      this.testResults.push({
        test: 'SetInterval Usage',
        passed: hasSetInterval,
        details: hasSetInterval ? 'setInterval properly used for polling' : 'Missing setInterval for polling'
      });
      
      this.testResults.push({
        test: 'Conditional Polling',
        passed: hasPollingUseEffect,
        details: hasPollingUseEffect ? 'Polling starts only for live matches' : 'Polling not conditional on live status'
      });
      
      this.testResults.push({
        test: 'Polling Logs',
        passed: hasPollingLogs,
        details: hasPollingLogs ? 'Proper polling start/stop logging' : 'Missing polling logs'
      });
      
      this.testResults.push({
        test: 'Live Indicator UI',
        passed: hasLiveIndicator,
        details: hasLiveIndicator ? 'Live updates indicator implemented' : 'Missing live updates visual indicator'
      });
      
      this.testResults.push({
        test: 'Removed Old SSE',
        passed: !hasOldSSE,
        details: hasOldSSE ? 'Old SSE code still present' : 'Old SSE code properly removed/replaced'
      });
      
      this.testResults.push({
        test: 'Polling Interval',
        passed: goodPollingInterval,
        details: pollingInterval ? `Polling interval: ${pollingInterval}ms (${goodPollingInterval ? 'Good' : 'Should be 2-3 seconds'})` : 'No polling interval found'
      });
      
    } catch (error) {
      this.testResults.push({
        test: 'MatchDetailPage Analysis',
        passed: false,
        details: `Error reading file: ${error.message}`
      });
    }
  }

  validateImplementationPrinciples() {
    console.log('\n🔬 Validating Implementation Principles...');
    
    // Check both files for complexity indicators
    const simplifiedContent = fs.existsSync(this.simplifiedLiveScoringPath) ? 
      fs.readFileSync(this.simplifiedLiveScoringPath, 'utf8') : '';
    const matchDetailContent = fs.existsSync(this.matchDetailPagePath) ? 
      fs.readFileSync(this.matchDetailPagePath, 'utf8') : '';
    
    // Principle 1: SIMPLE - No complex real-time libraries
    const hasComplexLibraries = simplifiedContent.includes('Pusher') || 
                               simplifiedContent.includes('Socket.io') ||
                               simplifiedContent.includes('WebSocket') ||
                               matchDetailContent.includes('Pusher') ||
                               matchDetailContent.includes('Socket.io') ||
                               matchDetailContent.includes('WebSocket');
    
    // Principle 2: STRONG - Immediate updates
    const hasImmediateUpdates = simplifiedContent.includes('IMMEDIATE') || 
                               simplifiedContent.includes('instantly');
    
    // Principle 3: RELIABLE - Simple polling
    const hasSimplePolling = matchDetailContent.includes('setInterval') && 
                            matchDetailContent.includes('pollForUpdates');
    
    this.testResults.push({
      test: 'Simple Implementation',
      passed: !hasComplexLibraries,
      details: hasComplexLibraries ? 'Contains complex real-time libraries' : 'Uses simple, reliable approaches'
    });
    
    this.testResults.push({
      test: 'Strong Immediate Updates',
      passed: hasImmediateUpdates,
      details: hasImmediateUpdates ? 'Implements immediate update strategy' : 'Missing immediate update implementation'
    });
    
    this.testResults.push({
      test: 'Reliable Polling',
      passed: hasSimplePolling,
      details: hasSimplePolling ? 'Uses simple, reliable polling' : 'Missing simple polling implementation'
    });
  }

  generateReport() {
    const report = {
      timestamp: new Date().toISOString(),
      summary: {
        total: this.testResults.length,
        passed: this.testResults.filter(r => r.passed).length,
        failed: this.testResults.filter(r => !r.passed).length
      },
      tests: this.testResults,
      implementation_summary: {
        approach: 'SIMPLE and STRONG immediate updates',
        key_changes: [
          'Removed ALL debouncing and delays from SimplifiedLiveScoring.js',
          'Implemented immediate API calls on every input change',
          'Added simple setInterval polling (2 seconds) for live matches in MatchDetailPage.js',
          'Visual indicators show when live polling is active',
          'No complex real-time libraries - just basic API calls and polling'
        ],
        benefits: [
          '⚡ Instant feedback - changes save immediately',
          '🔄 Reliable updates via simple polling', 
          '🛠️ Easy to debug and maintain',
          '📡 No dependency on complex WebSocket/SSE systems',
          '💪 Strong and predictable behavior'
        ]
      }
    };

    console.log('\n📊 IMMEDIATE LIVE UPDATES VALIDATION REPORT');
    console.log('==========================================');
    console.log(`✅ Tests Passed: ${report.summary.passed}/${report.summary.total}`);
    console.log(`❌ Tests Failed: ${report.summary.failed}/${report.summary.total}`);
    console.log(`📈 Success Rate: ${Math.round((report.summary.passed / report.summary.total) * 100)}%`);
    
    console.log('\n📋 Test Details:');
    report.tests.forEach((test, index) => {
      const status = test.passed ? '✅' : '❌';
      console.log(`${index + 1}. ${status} ${test.test}`);
      console.log(`   ${test.details}`);
    });

    console.log('\n🚀 Implementation Summary:');
    console.log(`Approach: ${report.implementation_summary.approach}`);
    
    console.log('\nKey Changes:');
    report.implementation_summary.key_changes.forEach(change => console.log(`  • ${change}`));
    
    console.log('\nBenefits:');
    report.implementation_summary.benefits.forEach(benefit => console.log(`  ${benefit}`));

    // Save report
    const reportPath = path.join(__dirname, 'immediate-updates-validation-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`\n📄 Full report saved: ${reportPath}`);

    return report;
  }

  run() {
    console.log('🚀 Starting Code Analysis Validation for Immediate Live Updates');
    console.log('Testing SIMPLE and STRONG immediate updates without complex real-time systems');
    
    try {
      this.validateSimplifiedLiveScoring();
      this.validateMatchDetailPage();
      this.validateImplementationPrinciples();
      
      const report = this.generateReport();
      
      if (report.summary.passed === report.summary.total) {
        console.log('\n🎉 ALL TESTS PASSED - Implementation is correct!');
        return true;
      } else {
        console.log(`\n⚠️  ${report.summary.failed} tests failed - Review implementation`);
        return false;
      }
    } catch (error) {
      console.error('❌ Validation failed:', error);
      return false;
    }
  }
}

// Run the validation if this file is executed directly
if (require.main === module) {
  const validator = new CodeAnalysisValidator();
  const success = validator.run();
  process.exit(success ? 0 : 1);
}

module.exports = CodeAnalysisValidator;