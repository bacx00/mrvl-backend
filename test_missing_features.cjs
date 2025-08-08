const axios = require('axios');
const fs = require('fs');
const API_BASE = 'http://localhost:8000/api';

async function testMissingFeatures() {
  console.log('Testing for missing features...\n');
  
  let issues = [];
  let successes = [];
  
  // Test 1: Check event logos in matches
  try {
    const matches = await axios.get(`${API_BASE}/public/matches`);
    const hasData = matches.data.data && Array.isArray(matches.data.data);
    if (hasData) {
      const totalMatches = matches.data.data.length;
      const matchesWithEventLogos = matches.data.data.filter(m => m.event && m.event.logo).length;
      
      if (matchesWithEventLogos < totalMatches) {
        issues.push(`Event logos missing: ${totalMatches - matchesWithEventLogos}/${totalMatches} matches lack event logos`);
      } else {
        successes.push(`All ${totalMatches} matches have event logos`);
      }
    }
  } catch(e) {
    issues.push('Could not check event logos: ' + e.message);
  }

  // Test 2: Check player match history with hero stats
  try {
    const playerMatches = await axios.get(`${API_BASE}/public/players/679/matches`);
    const matches = playerMatches.data.data || [];
    
    if (matches.length > 0) {
      const firstMatch = matches[0];
      const hasRequiredStats = firstMatch.kills !== undefined && 
                               firstMatch.deaths !== undefined && 
                               firstMatch.assists !== undefined &&
                               firstMatch.damage !== undefined &&
                               firstMatch.healing !== undefined &&
                               firstMatch.blocked !== undefined;
      
      if (hasRequiredStats) {
        successes.push('Player match history has all required stats (K/D/A/DMG/Heal/BLK)');
      } else {
        issues.push('Player match history missing some stats');
      }
      
      const hasHeroImages = firstMatch.hero_image !== undefined;
      if (hasHeroImages) {
        successes.push('Hero images included in match history');
      } else {
        issues.push('Hero images missing from match history');
      }
    }
  } catch(e) {
    issues.push('Could not check player match history: ' + e.message);
  }

  // Test 3: Check team achievements structure
  try {
    const teamAchievements = await axios.get(`${API_BASE}/public/teams/54/achievements`);
    const achievements = teamAchievements.data.data || [];
    
    if (achievements.length > 0) {
      successes.push(`Team has ${achievements.length} achievements available`);
    } else {
      successes.push('Team achievements endpoint working (no achievements yet)');
    }
  } catch(e) {
    issues.push('Could not check team achievements: ' + e.message);
  }

  // Test 4: Check frontend component structure
  try {
    const playerPage = fs.readFileSync('/var/www/mrvl-frontend/frontend/src/components/pages/PlayerDetailPage.js', 'utf8');
    const teamPage = fs.readFileSync('/var/www/mrvl-frontend/frontend/src/components/pages/TeamDetailPage.js', 'utf8');
    
    // Check PlayerDetailPage
    if (playerPage.includes('Team History') && playerPage.includes('Current')) {
      successes.push('PlayerDetailPage has Team History section with Current team');
    } else {
      issues.push('PlayerDetailPage missing proper Team History structure');
    }
    
    if (playerPage.includes('Player History') && playerPage.includes('KDA') && playerPage.includes('DMG')) {
      successes.push('PlayerDetailPage has Player History table with stats');
    } else {
      issues.push('PlayerDetailPage missing Player History table');
    }
    
    // Check TeamDetailPage
    const mentionsIndex = teamPage.indexOf('MentionsSection');
    const achievementsIndex = teamPage.indexOf('Achievements');
    
    if (mentionsIndex > 0 && achievementsIndex > mentionsIndex) {
      successes.push('TeamDetailPage has achievements below mentions section');
    } else {
      issues.push('TeamDetailPage achievements not properly positioned');
    }
  } catch(e) {
    issues.push('Could not check frontend components: ' + e.message);
  }

  // Report results
  console.log('\n=== TEST RESULTS ===\n');
  console.log(`✅ SUCCESSES (${successes.length}):`);
  successes.forEach(s => console.log(`  - ${s}`));
  
  console.log(`\n❌ ISSUES TO FIX (${issues.length}):`);
  issues.forEach(i => console.log(`  - ${i}`));
  
  const successRate = (successes.length / (successes.length + issues.length)) * 100;
  console.log(`\nSUCCESS RATE: ${successRate.toFixed(1)}%`);
  
  return { issues, successes, successRate };
}

testMissingFeatures().catch(console.error);