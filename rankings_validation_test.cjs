#!/usr/bin/env node

/**
 * Comprehensive Rankings System Validation Test
 * Tests all ranking endpoints and validates data integrity
 */

const axios = require('axios');

const BASE_URL = 'http://localhost:8000/api/public';
const ENDPOINTS = {
    playerRankings: '/rankings',
    teamRankings: '/team-rankings',
    playerDetail: '/rankings',
    teamDetail: '/team-rankings',
    rankDistribution: '/rankings/distribution',
    marvelRivalsInfo: '/rankings/marvel-rivals-info',
    topEarners: '/team-rankings/top-earners'
};

async function testEndpoint(name, url, params = {}) {
    try {
        console.log(`\n🧪 Testing ${name}...`);
        const response = await axios.get(`${BASE_URL}${url}`, { params });
        
        if (response.status === 200 && response.data.success !== false) {
            console.log(`✅ ${name} - OK`);
            return response.data;
        } else {
            console.log(`❌ ${name} - Failed:`, response.data.message || 'Unknown error');
            return null;
        }
    } catch (error) {
        console.log(`❌ ${name} - Error:`, error.message);
        return null;
    }
}

function validatePlayerData(players, context) {
    console.log(`\n📊 Validating ${context} player data...`);
    
    if (!players || !Array.isArray(players) || players.length === 0) {
        console.log(`❌ No player data found for ${context}`);
        return false;
    }
    
    let validationResults = {
        peakRatingFixed: 0,
        hasRankingData: 0,
        hasTeamData: 0,
        regionFiltered: 0
    };
    
    players.forEach((player, index) => {
        // Check peak rating is at least equal to current rating
        if (player.ranking && player.ranking.peak_rating >= player.ranking.rating) {
            validationResults.peakRatingFixed++;
        }
        
        // Check ranking data structure
        if (player.ranking && player.ranking.rank && player.ranking.division !== undefined) {
            validationResults.hasRankingData++;
        }
        
        // Check team data structure
        if (player.team && player.team.name) {
            validationResults.hasTeamData++;
        }
        
        if (index < 3) {
            console.log(`   Player ${index + 1}: ${player.username} (${player.region}) - Rating: ${player.ranking?.rating}, Peak: ${player.ranking?.peak_rating}`);
        }
    });
    
    console.log(`   ✅ Peak rating fixed: ${validationResults.peakRatingFixed}/${players.length}`);
    console.log(`   ✅ Has ranking data: ${validationResults.hasRankingData}/${players.length}`);
    console.log(`   ✅ Has team data: ${validationResults.hasTeamData}/${players.length}`);
    
    return validationResults;
}

function validateTeamData(teams, context) {
    console.log(`\n📊 Validating ${context} team data...`);
    
    if (!teams || !Array.isArray(teams) || teams.length === 0) {
        console.log(`❌ No team data found for ${context}`);
        return false;
    }
    
    let validationResults = {
        hasWinRate: 0,
        hasEarnings: 0,
        hasRosterCount: 0
    };
    
    teams.forEach((team, index) => {
        // Check win rate calculation
        if (team.win_rate !== undefined && team.win_rate >= 0) {
            validationResults.hasWinRate++;
        }
        
        // Check earnings data
        if (team.earnings !== undefined && team.earnings !== null) {
            validationResults.hasEarnings++;
        }
        
        // Check roster count
        if (team.active_roster !== undefined && team.active_roster > 0) {
            validationResults.hasRosterCount++;
        }
        
        if (index < 3) {
            console.log(`   Team ${index + 1}: ${team.name} (${team.region}) - Rating: ${team.rating}, Win Rate: ${team.win_rate}%, Roster: ${team.active_roster}`);
        }
    });
    
    console.log(`   ✅ Has win rate: ${validationResults.hasWinRate}/${teams.length}`);
    console.log(`   ✅ Has earnings: ${validationResults.hasEarnings}/${teams.length}`);
    console.log(`   ✅ Has roster count: ${validationResults.hasRosterCount}/${teams.length}`);
    
    return validationResults;
}

async function runComprehensiveTests() {
    console.log('🚀 Starting Comprehensive Rankings System Validation\n');
    
    // Test 1: Basic Player Rankings
    const playerRankings = await testEndpoint('Player Rankings', ENDPOINTS.playerRankings);
    if (playerRankings) {
        validatePlayerData(playerRankings.data, 'Global');
        console.log(`   📄 Pagination: ${playerRankings.pagination.current_page}/${playerRankings.pagination.last_page} (${playerRankings.pagination.total} total)`);
    }
    
    // Test 2: Regional Player Rankings
    const naPlayers = await testEndpoint('NA Player Rankings', ENDPOINTS.playerRankings, { region: 'na', limit: 10 });
    if (naPlayers) {
        validatePlayerData(naPlayers.data, 'NA Regional');
        console.log(`   🌎 Region Filter: All players are from NA region: ${naPlayers.data.every(p => p.region === 'NA')}`);
    }
    
    // Test 3: Player Rankings with Pagination
    const pagedPlayers = await testEndpoint('Paginated Player Rankings', ENDPOINTS.playerRankings, { limit: 5, page: 2 });
    if (pagedPlayers) {
        console.log(`   📄 Pagination Test: Requested 5 per page, got ${pagedPlayers.pagination.per_page} per page`);
        console.log(`   📄 Current page: ${pagedPlayers.pagination.current_page}, Total pages: ${pagedPlayers.pagination.last_page}`);
    }
    
    // Test 4: Team Rankings
    const teamRankings = await testEndpoint('Team Rankings', ENDPOINTS.teamRankings);
    if (teamRankings) {
        validateTeamData(teamRankings.data, 'Global');
    }
    
    // Test 5: Regional Team Rankings
    const asiaTeams = await testEndpoint('Asia Team Rankings', ENDPOINTS.teamRankings, { region: 'asia', limit: 5 });
    if (asiaTeams) {
        validateTeamData(asiaTeams.data, 'Asia Regional');
        console.log(`   🌏 Region Filter: All teams from Asia region: ${asiaTeams.data.every(t => ['ASIA', 'CN'].includes(t.region))}`);
    }
    
    // Test 6: Team Rankings Sorted by Earnings
    const topEarningTeams = await testEndpoint('Teams by Earnings', ENDPOINTS.teamRankings, { sort: 'earnings', limit: 5 });
    if (topEarningTeams) {
        console.log(`   💰 Earnings sorting: Top team earned $${topEarningTeams.data[0].earnings}`);
        const earningsDescending = topEarningTeams.data.every((team, i) => 
            i === 0 || parseFloat(team.earnings) <= parseFloat(topEarningTeams.data[i-1].earnings)
        );
        console.log(`   💰 Earnings properly sorted: ${earningsDescending}`);
    }
    
    // Test 7: Rank Distribution
    const rankDistribution = await testEndpoint('Rank Distribution', ENDPOINTS.rankDistribution);
    if (rankDistribution) {
        console.log(`   📊 Total ranks: ${rankDistribution.data.length}`);
        console.log(`   📊 Total players in distribution: ${rankDistribution.total_players}`);
        const goldRank = rankDistribution.data.find(rank => rank.rank === 'gold');
        if (goldRank) {
            console.log(`   📊 Gold rank: ${goldRank.count} players (${goldRank.percentage}%)`);
        }
    }
    
    // Test 8: Marvel Rivals Info
    const marvelInfo = await testEndpoint('Marvel Rivals Info', ENDPOINTS.marvelRivalsInfo);
    if (marvelInfo) {
        console.log(`   🎮 Total ranks: ${marvelInfo.data.ranking_system.total_ranks}`);
        console.log(`   🎮 Season reset: ${marvelInfo.data.ranking_system.season_reset}`);
        console.log(`   🎮 Starting rank: ${marvelInfo.data.ranking_system.starting_rank}`);
    }
    
    // Test 9: Top Earners
    const topEarners = await testEndpoint('Top Earners', ENDPOINTS.topEarners);
    if (topEarners) {
        console.log(`   💰 Top earning team: ${topEarners.data[0].name} - $${topEarners.data[0].earnings}`);
        const earningsDescending = topEarners.data.every((team, i) => 
            i === 0 || parseFloat(team.earnings) <= parseFloat(topEarners.data[i-1].earnings)
        );
        console.log(`   💰 Top earners properly sorted: ${earningsDescending}`);
    }
    
    // Test 10: Individual Player Details
    if (playerRankings && playerRankings.data.length > 0) {
        const firstPlayerId = playerRankings.data[0].id;
        const playerDetail = await testEndpoint('Player Details', `${ENDPOINTS.playerDetail}/${firstPlayerId}`);
        if (playerDetail) {
            console.log(`   👤 Player details: ${playerDetail.data.username}`);
            console.log(`   👤 Competitive stats: ${playerDetail.data.competitive_stats.matches_played} matches played`);
            console.log(`   👤 Marvel Rivals features: Hero bans unlocked = ${playerDetail.data.marvel_rivals_features.hero_bans_unlocked}`);
        }
    }
    
    // Test 11: Individual Team Details
    if (teamRankings && teamRankings.data.length > 0) {
        const firstTeamId = teamRankings.data[0].id;
        const teamDetail = await testEndpoint('Team Details', `${ENDPOINTS.teamDetail}/${firstTeamId}`);
        if (teamDetail) {
            console.log(`   🏆 Team details: ${teamDetail.data.name}`);
            console.log(`   🏆 Global rank: ${teamDetail.data.ranking.global_rank}`);
            console.log(`   🏆 Active roster: ${teamDetail.data.roster.length} players`);
        }
    }
    
    console.log('\n🎉 Comprehensive Rankings System Validation Complete!');
}

// Run the tests
runComprehensiveTests().catch(console.error);