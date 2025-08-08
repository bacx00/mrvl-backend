// Test script to verify match score synchronization
const fetch = require('node-fetch');

const BACKEND_URL = 'https://staging.mrvl.net';
const testMatchId = 1; // Replace with actual match ID

async function testMatchScoreFlow() {
    console.log('🧪 Testing Match Score Synchronization Flow...\n');
    
    try {
        // 1. Get match data from the API
        console.log('1. Fetching match data from API...');
        const response = await fetch(`${BACKEND_URL}/api/matches/${testMatchId}`);
        const matchData = await response.json();
        
        console.log('API Response structure:', JSON.stringify(matchData, null, 2));
        
        // 2. Check if scores are present
        console.log('\n2. Score Analysis:');
        console.log('- Direct scores:', {
            team1_score: matchData.team1_score,
            team2_score: matchData.team2_score
        });
        
        console.log('- Score object:', matchData.score);
        console.log('- Scores object:', matchData.scores);
        
        // 3. Check match status
        console.log('\n3. Match Status:', matchData.status || matchData.match_info?.status);
        
        // 4. Check maps data for individual scores
        console.log('\n4. Maps Data:');
        if (matchData.maps_data || matchData.score?.maps || matchData.maps) {
            const maps = matchData.maps_data || matchData.score?.maps || matchData.maps;
            console.log('Maps with scores:', maps?.map(map => ({
                map_name: map.map_name,
                team1_score: map.team1_score,
                team2_score: map.team2_score,
                winner_id: map.winner_id,
                status: map.status
            })));
        } else {
            console.log('No maps data found');
        }
        
    } catch (error) {
        console.error('❌ Test failed:', error);
    }
}

testMatchScoreFlow();