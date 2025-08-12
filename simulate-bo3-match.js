// Complete BO3 Match Simulation: 100 Thieves vs EDward Gaming
// This script simulates a full Best-of-3 match with different hero compositions and stats per map

const API_BASE = 'https://staging.mrvl.net/api';

// Helper function to delay between updates
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// Helper to make API calls
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    const response = await fetch(`${API_BASE}${endpoint}`, options);
    return response.json();
}

// Create a new BO3 match
async function createMatch() {
    console.log('📋 Creating new BO3 match: 100 Thieves vs EDward Gaming');
    
    const matchData = {
        team1_id: 4,  // 100 Thieves
        team2_id: 2,  // EDward Gaming
        event_id: 1,
        format: 'BO3',
        status: 'upcoming',
        scheduled_at: new Date().toISOString(),
        match_info: {
            stream_url: 'https://twitch.tv/mrvl_esports',
            venue: 'Los Angeles Arena',
            casters: ['Alex "Goldenboy" Mendez', 'Mitch "Uber" Leslie']
        }
    };
    
    const result = await apiCall('/admin/matches', 'POST', matchData);
    console.log('✅ Match created with ID:', result.id);
    return result.id;
}

// Start the match and initialize maps
async function startMatch(matchId) {
    console.log('🎮 Starting match and initializing 3 maps');
    
    const updateData = {
        status: 'live',
        team1_score: 0,
        team2_score: 0,
        current_map: 1,
        maps_data: [
            {
                map_name: 'Tokyo 2099: Shibuya',
                mode: 'Convoy',
                index: 1,
                status: 'upcoming',
                team1_score: 0,
                team2_score: 0,
                team1_composition: [],
                team2_composition: []
            },
            {
                map_name: 'Wakanda: Birnin T\'Challa', 
                mode: 'Domination',
                index: 2,
                status: 'upcoming',
                team1_score: 0,
                team2_score: 0,
                team1_composition: [],
                team2_composition: []
            },
            {
                map_name: 'Asgard: Throne Room',
                mode: 'Convergence',
                index: 3,
                status: 'upcoming',
                team1_score: 0,
                team2_score: 0,
                team1_composition: [],
                team2_composition: []
            }
        ]
    };
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', updateData);
    console.log('✅ Match started with 3 maps initialized');
}

// Simulate Map 1: Tokyo - Convoy (100T wins 3-2)
async function simulateMap1(matchId) {
    console.log('\n🗾 MAP 1: Tokyo 2099 - Convoy Mode');
    await delay(1000);
    
    // Set hero compositions for Map 1
    console.log('Setting hero compositions...');
    let mapsData = [
        {
            map_name: 'Tokyo 2099: Shibuya',
            mode: 'Convoy',
            index: 1,
            status: 'live',
            team1_score: 0,
            team2_score: 0,
            team1_composition: [
                { player_id: 410, name: 'vinnie', hero: 'Spider-Man', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 407, name: 'hxrvey', hero: 'Adam Warlock', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 409, name: 'cj', hero: 'Venom', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 411, name: 'vanguard_player', hero: 'Magneto', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 412, name: 'flex_player', hero: 'Iron Man', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 413, name: 'support_player', hero: 'Luna Snow', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
            ],
            team2_composition: [
                { player_id: 101, name: 'EDG_DPS1', hero: 'Star-Lord', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 102, name: 'EDG_DPS2', hero: 'Black Widow', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 103, name: 'EDG_Tank', hero: 'Hulk', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 104, name: 'EDG_Support1', hero: 'Mantis', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 105, name: 'EDG_Support2', hero: 'Rocket Raccoon', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
                { player_id: 106, name: 'EDG_Flex', hero: 'Thor', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
            ]
        },
        // Keep other maps as upcoming
        {
            map_name: 'Wakanda: Birnin T\'Challa',
            mode: 'Domination',
            index: 2,
            status: 'upcoming',
            team1_score: 0,
            team2_score: 0,
            team1_composition: [],
            team2_composition: []
        },
        {
            map_name: 'Asgard: Throne Room',
            mode: 'Convergence',
            index: 3,
            status: 'upcoming',
            team1_score: 0,
            team2_score: 0,
            team1_composition: [],
            team2_composition: []
        }
    ];
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        current_map: 1,
        maps_data: mapsData 
    });
    
    // Simulate round progression
    console.log('Round 1: 100T takes first point');
    await delay(2000);
    mapsData[0].team1_score = 1;
    mapsData[0].team1_composition[0].eliminations = 8;  // vinnie
    mapsData[0].team1_composition[0].deaths = 2;
    mapsData[0].team1_composition[0].assists = 3;
    mapsData[0].team1_composition[0].damage = 4500;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 2: EDG strikes back');
    await delay(2000);
    mapsData[0].team2_score = 1;
    mapsData[0].team2_composition[0].eliminations = 10;
    mapsData[0].team2_composition[0].deaths = 3;
    mapsData[0].team2_composition[0].damage = 5200;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 3: EDG takes the lead');
    await delay(2000);
    mapsData[0].team2_score = 2;
    mapsData[0].team2_composition[1].eliminations = 7;
    mapsData[0].team2_composition[1].deaths = 4;
    mapsData[0].team2_composition[1].damage = 3800;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 4: 100T equalizes!');
    await delay(2000);
    mapsData[0].team1_score = 2;
    mapsData[0].team1_composition[1].eliminations = 2;  // hxrvey (support)
    mapsData[0].team1_composition[1].deaths = 5;
    mapsData[0].team1_composition[1].assists = 18;
    mapsData[0].team1_composition[1].healing = 12000;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 5: 100T clutches Map 1!');
    await delay(2000);
    mapsData[0].team1_score = 3;
    mapsData[0].winner = 'team1';
    mapsData[0].status = 'completed';
    
    // Final stats for Map 1
    mapsData[0].team1_composition[0].eliminations = 23;  // vinnie
    mapsData[0].team1_composition[0].deaths = 8;
    mapsData[0].team1_composition[0].assists = 7;
    mapsData[0].team1_composition[0].damage = 13500;
    
    mapsData[0].team1_composition[1].eliminations = 4;   // hxrvey
    mapsData[0].team1_composition[1].deaths = 9;
    mapsData[0].team1_composition[1].assists = 32;
    mapsData[0].team1_composition[1].healing = 28000;
    
    mapsData[0].team1_composition[2].eliminations = 18;  // cj
    mapsData[0].team1_composition[2].deaths = 10;
    mapsData[0].team1_composition[2].assists = 12;
    mapsData[0].team1_composition[2].damage = 11000;
    mapsData[0].team1_composition[2].damage_blocked = 8500;
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        team1_score: 1,
        team2_score: 0,
        maps_data: mapsData 
    });
    
    console.log('✅ Map 1 Complete: 100 Thieves wins 3-2!');
}

// Simulate Map 2: Wakanda - Domination (EDG wins 100-85)
async function simulateMap2(matchId) {
    console.log('\n🌍 MAP 2: Wakanda - Domination Mode');
    await delay(2000);
    
    // Get current match data
    const match = await apiCall(`/matches/${matchId}`);
    let mapsData = match.data.maps_data || match.maps_data;
    
    // Hero swaps for Map 2
    console.log('Hero compositions for Map 2...');
    mapsData[1] = {
        map_name: 'Wakanda: Birnin T\'Challa',
        mode: 'Domination',
        index: 2,
        status: 'live',
        team1_score: 0,
        team2_score: 0,
        team1_composition: [
            { player_id: 410, name: 'vinnie', hero: 'Black Panther', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 407, name: 'hxrvey', hero: 'Cloak & Dagger', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 409, name: 'cj', hero: 'Captain America', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 411, name: 'vanguard_player', hero: 'Doctor Strange', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 412, name: 'flex_player', hero: 'Hawkeye', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 413, name: 'support_player', hero: 'Jeff the Land Shark', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
        ],
        team2_composition: [
            { player_id: 101, name: 'EDG_DPS1', hero: 'Psylocke', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 102, name: 'EDG_DPS2', hero: 'Winter Soldier', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 103, name: 'EDG_Tank', hero: 'Groot', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 104, name: 'EDG_Support1', hero: 'Loki', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 105, name: 'EDG_Support2', hero: 'Invisible Woman', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 106, name: 'EDG_Flex', hero: 'Peni Parker', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
        ]
    };
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        current_map: 2,
        maps_data: mapsData 
    });
    
    // Simulate domination point captures
    console.log('EDG captures point A');
    await delay(2000);
    mapsData[1].team2_score = 25;
    mapsData[1].team2_composition[0].eliminations = 12;
    mapsData[1].team2_composition[0].deaths = 4;
    mapsData[1].team2_composition[0].damage = 7800;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('100T fights back, captures B');
    await delay(2000);
    mapsData[1].team1_score = 30;
    mapsData[1].team1_composition[0].eliminations = 15;
    mapsData[1].team1_composition[0].deaths = 6;
    mapsData[1].team1_composition[0].damage = 8200;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Intense mid-game battle');
    await delay(2000);
    mapsData[1].team1_score = 55;
    mapsData[1].team2_score = 60;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('EDG takes control in late game');
    await delay(2000);
    mapsData[1].team2_score = 85;
    mapsData[1].team1_score = 70;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('EDG wins Map 2!');
    await delay(2000);
    mapsData[1].team2_score = 100;
    mapsData[1].team1_score = 85;
    mapsData[1].winner = 'team2';
    mapsData[1].status = 'completed';
    
    // Final stats for Map 2
    mapsData[1].team1_composition[0].eliminations = 28;  // vinnie on Black Panther
    mapsData[1].team1_composition[0].deaths = 12;
    mapsData[1].team1_composition[0].assists = 9;
    mapsData[1].team1_composition[0].damage = 18500;
    
    mapsData[1].team1_composition[1].eliminations = 8;   // hxrvey on Cloak & Dagger
    mapsData[1].team1_composition[1].deaths = 7;
    mapsData[1].team1_composition[1].assists = 28;
    mapsData[1].team1_composition[1].healing = 32000;
    
    mapsData[1].team2_composition[0].eliminations = 35;  // EDG_DPS1 dominates
    mapsData[1].team2_composition[0].deaths = 9;
    mapsData[1].team2_composition[0].assists = 11;
    mapsData[1].team2_composition[0].damage = 22000;
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        team1_score: 1,
        team2_score: 1,
        maps_data: mapsData 
    });
    
    console.log('✅ Map 2 Complete: EDward Gaming wins 100-85! Series tied 1-1');
}

// Simulate Map 3: Asgard - Convergence (100T wins 3-1)
async function simulateMap3(matchId) {
    console.log('\n⚡ MAP 3: Asgard - Convergence Mode (Decider)');
    await delay(2000);
    
    // Get current match data
    const match = await apiCall(`/matches/${matchId}`);
    let mapsData = match.data.maps_data || match.maps_data;
    
    // Final hero compositions for Map 3
    console.log('Final compositions for the decider map...');
    mapsData[2] = {
        map_name: 'Asgard: Throne Room',
        mode: 'Convergence',
        index: 3,
        status: 'live',
        team1_score: 0,
        team2_score: 0,
        team1_composition: [
            { player_id: 410, name: 'vinnie', hero: 'Spider-Man', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 407, name: 'hxrvey', hero: 'Adam Warlock', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 409, name: 'cj', hero: 'Venom', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 411, name: 'vanguard_player', hero: 'Magneto', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 412, name: 'flex_player', hero: 'Scarlet Witch', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 413, name: 'support_player', hero: 'Luna Snow', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
        ],
        team2_composition: [
            { player_id: 101, name: 'EDG_DPS1', hero: 'Star-Lord', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 102, name: 'EDG_DPS2', hero: 'Moon Knight', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 103, name: 'EDG_Tank', hero: 'Hulk', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 104, name: 'EDG_Support1', hero: 'Mantis', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 105, name: 'EDG_Support2', hero: 'Rocket Raccoon', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 },
            { player_id: 106, name: 'EDG_Flex', hero: 'Storm', eliminations: 0, deaths: 0, assists: 0, damage: 0, healing: 0, damage_blocked: 0 }
        ]
    };
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        current_map: 3,
        maps_data: mapsData 
    });
    
    // Simulate convergence rounds
    console.log('Round 1: 100T dominates with perfect teamwork');
    await delay(2000);
    mapsData[2].team1_score = 1;
    mapsData[2].team1_composition[0].eliminations = 10;
    mapsData[2].team1_composition[0].deaths = 1;
    mapsData[2].team1_composition[0].damage = 6000;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 2: EDG responds with aggression');
    await delay(2000);
    mapsData[2].team2_score = 1;
    mapsData[2].team2_composition[1].eliminations = 13;
    mapsData[2].team2_composition[1].deaths = 5;
    mapsData[2].team2_composition[1].damage = 7500;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 3: 100T with the clutch play!');
    await delay(2000);
    mapsData[2].team1_score = 2;
    mapsData[2].team1_composition[2].eliminations = 16;  // cj pops off
    mapsData[2].team1_composition[2].deaths = 4;
    mapsData[2].team1_composition[2].damage = 9500;
    mapsData[2].team1_composition[2].damage_blocked = 12000;
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { maps_data: mapsData });
    
    console.log('Round 4: 100T closes out the series!');
    await delay(2000);
    mapsData[2].team1_score = 3;
    mapsData[2].winner = 'team1';
    mapsData[2].status = 'completed';
    
    // Final stats for Map 3
    mapsData[2].team1_composition[0].eliminations = 31;  // vinnie MVP performance
    mapsData[2].team1_composition[0].deaths = 7;
    mapsData[2].team1_composition[0].assists = 14;
    mapsData[2].team1_composition[0].damage = 21000;
    
    mapsData[2].team1_composition[1].eliminations = 6;   // hxrvey clutch support
    mapsData[2].team1_composition[1].deaths = 8;
    mapsData[2].team1_composition[1].assists = 38;
    mapsData[2].team1_composition[1].healing = 35000;
    
    mapsData[2].team1_composition[2].eliminations = 24;  // cj tank dominance
    mapsData[2].team1_composition[2].deaths = 11;
    mapsData[2].team1_composition[2].assists = 19;
    mapsData[2].team1_composition[2].damage = 15500;
    mapsData[2].team1_composition[2].damage_blocked = 28000;
    
    mapsData[2].team1_composition[3].eliminations = 20;  // vanguard_player
    mapsData[2].team1_composition[3].deaths = 9;
    mapsData[2].team1_composition[3].assists = 16;
    mapsData[2].team1_composition[3].damage = 13000;
    mapsData[2].team1_composition[3].damage_blocked = 18000;
    
    mapsData[2].team1_composition[4].eliminations = 27;  // flex_player
    mapsData[2].team1_composition[4].deaths = 10;
    mapsData[2].team1_composition[4].assists = 8;
    mapsData[2].team1_composition[4].damage = 17000;
    
    mapsData[2].team1_composition[5].eliminations = 3;   // support_player
    mapsData[2].team1_composition[5].deaths = 6;
    mapsData[2].team1_composition[5].assists = 42;
    mapsData[2].team1_composition[5].healing = 38000;
    
    // EDG final stats
    mapsData[2].team2_composition[0].eliminations = 22;
    mapsData[2].team2_composition[0].deaths = 14;
    mapsData[2].team2_composition[0].assists = 10;
    mapsData[2].team2_composition[0].damage = 14500;
    
    mapsData[2].team2_composition[1].eliminations = 25;
    mapsData[2].team2_composition[1].deaths = 13;
    mapsData[2].team2_composition[1].assists = 7;
    mapsData[2].team2_composition[1].damage = 16000;
    
    await apiCall(`/admin/matches/${matchId}`, 'PUT', { 
        team1_score: 2,
        team2_score: 1,
        status: 'completed',
        maps_data: mapsData 
    });
    
    console.log('✅ Map 3 Complete: 100 Thieves wins 3-1!');
    console.log('🏆 MATCH COMPLETE: 100 Thieves defeats EDward Gaming 2-1 in the series!');
}

// Main simulation function
async function runFullBO3Simulation() {
    try {
        console.log('🎮 Starting Complete BO3 Match Simulation');
        console.log('=========================================\n');
        
        // Create the match
        const matchId = await createMatch();
        
        // Start the match
        await startMatch(matchId);
        
        // Simulate all 3 maps
        await simulateMap1(matchId);
        await simulateMap2(matchId);
        await simulateMap3(matchId);
        
        console.log('\n=========================================');
        console.log('🏆 FINAL RESULT: 100 Thieves 2-1 EDward Gaming');
        console.log('\nMap Results:');
        console.log('  Map 1 (Tokyo - Convoy): 100T 3-2 EDG');
        console.log('  Map 2 (Wakanda - Domination): EDG 100-85 100T');
        console.log('  Map 3 (Asgard - Convergence): 100T 3-1 EDG');
        console.log('\nMVP: vinnie (100 Thieves) - 82 eliminations across 3 maps');
        console.log('=========================================\n');
        
        console.log(`✅ Simulation complete! Visit: https://staging.mrvl.net/#match-detail/${matchId}`);
        console.log('Click on any player to see their individual performance data in their profile.');
        
    } catch (error) {
        console.error('❌ Error during simulation:', error);
    }
}

// Run the simulation
runFullBO3Simulation();