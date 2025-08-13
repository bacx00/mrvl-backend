/**
 * Real Marvel Rivals BO3 Match Simulation
 * Tests every single live update that would happen during a tournament
 */

const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Simulate localStorage for testing
const localStorage = {
  data: {},
  setItem(key, value) {
    this.data[key] = value;
    console.log(`📤 BROADCAST: ${key} = ${JSON.stringify(JSON.parse(value), null, 2)}`);
  },
  getItem(key) {
    return this.data[key] || null;
  }
};

class LiveMatchSimulator {
  constructor() {
    this.matchId = 1;
    this.currentMap = 1;
    this.matchData = {
      id: 1,
      status: 'scheduled',
      team1_score: 0,  // Series score
      team2_score: 0,  // Series score
      team1: { name: 'Sentinels', players: ['TenZ', 'Zekken', 'sAcY', 'pANcada', 'dephh', 'johnqt'] },
      team2: { name: '100 Thieves', players: ['Asuna', 'bang', 'Cryo', 'stellar', 'derrek', 'Boostio'] },
      maps: [
        { map_number: 1, name: 'Temple of Anubis', team1_score: 0, team2_score: 0, status: 'upcoming', winner: null },
        { map_number: 2, name: 'Hanamura', team1_score: 0, team2_score: 0, status: 'upcoming', winner: null },
        { map_number: 3, name: 'Volskaya', team1_score: 0, team2_score: 0, status: 'upcoming', winner: null }
      ],
      team1Players: [],
      team2Players: []
    };

    console.log('🎮 MARVEL RIVALS BO3 TOURNAMENT SIMULATION');
    console.log('==========================================');
    console.log('Match: Sentinels vs 100 Thieves');
    console.log('Format: Best of 3 Maps');
    console.log('');
  }

  broadcast(updateType, data) {
    const payload = {
      matchId: this.matchId,
      timestamp: Date.now(),
      type: updateType,
      data: data
    };
    localStorage.setItem(`live_match_${this.matchId}`, JSON.stringify(payload));
  }

  async waitForEnter(message) {
    return new Promise((resolve) => {
      rl.question(`${message} (Press Enter to continue...)`, () => {
        resolve();
      });
    });
  }

  async simulateMatchStart() {
    console.log('\n🚀 MATCH STARTING...');
    this.matchData.status = 'live';
    this.matchData.current_map = 1;
    this.matchData.maps[0].status = 'live';
    
    this.broadcast('match_start', {
      ...this.matchData,
      message: 'Match has started! Map 1 is now live.'
    });

    await this.waitForEnter('✅ Match status changed to LIVE');
  }

  async simulateHeroPicks(mapNumber) {
    console.log(`\n🦸 MAP ${mapNumber} HERO PICKS...`);
    
    const team1Heroes = ['Spider-Man', 'Iron Man', 'Hulk', 'Doctor Strange', 'Scarlet Witch', 'Black Widow'];
    const team2Heroes = ['Venom', 'Green Goblin', 'Magneto', 'Loki', 'Hela', 'Winter Soldier'];
    
    // Simulate hero picks happening one by one
    for (let i = 0; i < 6; i++) {
      console.log(`   Team 1 picks: ${team1Heroes[i]}`);
      this.matchData.team1Players = team1Heroes.slice(0, i + 1).map((hero, idx) => ({
        name: this.matchData.team1.players[idx],
        hero: hero,
        kills: 0,
        deaths: 0,
        damage: 0
      }));
      
      this.broadcast('hero_pick', {
        mapNumber,
        team: 1,
        hero: team1Heroes[i],
        player: this.matchData.team1.players[i],
        team1Players: this.matchData.team1Players,
        team2Players: this.matchData.team2Players
      });
      
      await new Promise(resolve => setTimeout(resolve, 500));
      
      if (i < 5) {
        console.log(`   Team 2 picks: ${team2Heroes[i]}`);
        this.matchData.team2Players = team2Heroes.slice(0, i + 1).map((hero, idx) => ({
          name: this.matchData.team2.players[idx],
          hero: hero,
          kills: 0,
          deaths: 0,
          damage: 0
        }));
        
        this.broadcast('hero_pick', {
          mapNumber,
          team: 2,
          hero: team2Heroes[i],
          player: this.matchData.team2.players[i],
          team1Players: this.matchData.team1Players,
          team2Players: this.matchData.team2Players
        });
        
        await new Promise(resolve => setTimeout(resolve, 500));
      }
    }
    
    // Final pick for team 2
    console.log(`   Team 2 picks: ${team2Heroes[5]}`);
    this.matchData.team2Players = team2Heroes.map((hero, idx) => ({
      name: this.matchData.team2.players[idx],
      hero: hero,
      kills: 0,
      deaths: 0,
      damage: 0
    }));
    
    this.broadcast('hero_pick_complete', {
      mapNumber,
      team1Players: this.matchData.team1Players,
      team2Players: this.matchData.team2Players,
      message: `Map ${mapNumber} hero picks completed!`
    });

    await this.waitForEnter(`✅ Map ${mapNumber} hero picks completed`);
  }

  async simulateMapProgress(mapNumber, finalScore1, finalScore2) {
    console.log(`\n⚔️  MAP ${mapNumber} LIVE PROGRESS...`);
    
    const map = this.matchData.maps[mapNumber - 1];
    let score1 = 0, score2 = 0;
    
    // Simulate round-by-round scoring
    const rounds = [
      [1, 0], [1, 1], [2, 1], [2, 2], [finalScore1, finalScore2]
    ];
    
    for (let i = 0; i < rounds.length; i++) {
      [score1, score2] = rounds[i];
      
      console.log(`   Round ${i + 1}: ${score1} - ${score2}`);
      
      map.team1_score = score1;
      map.team2_score = score2;
      
      // Simulate player stats update
      this.updatePlayerStats();
      
      this.broadcast('score_update', {
        mapNumber,
        team1_score: score1,
        team2_score: score2,
        maps: this.matchData.maps,
        team1Players: this.matchData.team1Players,
        team2Players: this.matchData.team2Players,
        message: `Map ${mapNumber}: ${score1}-${score2}`
      });
      
      await new Promise(resolve => setTimeout(resolve, 1000));
    }
    
    await this.waitForEnter(`✅ Map ${mapNumber} live scoring: ${finalScore1}-${finalScore2}`);
  }

  updatePlayerStats() {
    // Simulate realistic player stats updates
    this.matchData.team1Players.forEach(player => {
      player.kills += Math.floor(Math.random() * 3);
      player.deaths += Math.floor(Math.random() * 2);
      player.damage += Math.floor(Math.random() * 1000) + 500;
    });
    
    this.matchData.team2Players.forEach(player => {
      player.kills += Math.floor(Math.random() * 3);
      player.deaths += Math.floor(Math.random() * 2);  
      player.damage += Math.floor(Math.random() * 1000) + 500;
    });
  }

  async simulateMapCompletion(mapNumber, winner) {
    console.log(`\n🏆 MAP ${mapNumber} COMPLETED!`);
    
    const map = this.matchData.maps[mapNumber - 1];
    map.status = 'completed';
    map.winner = winner;
    
    // Update series score
    if (winner === 1) {
      this.matchData.team1_score++;
      console.log(`   ${this.matchData.team1.name} wins Map ${mapNumber}!`);
    } else {
      this.matchData.team2_score++;
      console.log(`   ${this.matchData.team2.name} wins Map ${mapNumber}!`);
    }
    
    console.log(`   Series Score: ${this.matchData.team1_score}-${this.matchData.team2_score}`);
    
    this.broadcast('map_complete', {
      mapNumber,
      winner,
      maps: this.matchData.maps,
      team1_score: this.matchData.team1_score,
      team2_score: this.matchData.team2_score,
      series_status: `${this.matchData.team1_score}-${this.matchData.team2_score}`,
      message: `Map ${mapNumber} completed! Series: ${this.matchData.team1_score}-${this.matchData.team2_score}`
    });

    await this.waitForEnter(`✅ Map ${mapNumber} completed. Series: ${this.matchData.team1_score}-${this.matchData.team2_score}`);
  }

  async simulateMatchCompletion() {
    console.log('\n🎉 MATCH COMPLETED!');
    
    this.matchData.status = 'completed';
    const winner = this.matchData.team1_score > this.matchData.team2_score ? 1 : 2;
    const winnerName = winner === 1 ? this.matchData.team1.name : this.matchData.team2.name;
    
    console.log(`   Winner: ${winnerName}`);
    console.log(`   Final Score: ${this.matchData.team1_score}-${this.matchData.team2_score}`);
    
    this.broadcast('match_complete', {
      status: 'completed',
      winner,
      winnerName,
      finalScore: `${this.matchData.team1_score}-${this.matchData.team2_score}`,
      maps: this.matchData.maps,
      team1Players: this.matchData.team1Players,
      team2Players: this.matchData.team2Players,
      message: `🏆 ${winnerName} wins ${this.matchData.team1_score}-${this.matchData.team2_score}!`
    });

    await this.waitForEnter('✅ Match completed!');
  }

  async runFullSimulation() {
    console.log('This simulation will show every live update that happens during a BO3 match:');
    console.log('- Match status changes');
    console.log('- Hero picks for each map');
    console.log('- Real-time round scores');
    console.log('- Player statistics updates');
    console.log('- Map completions and series progression');
    console.log('- Final match completion');
    console.log('');
    
    await this.waitForEnter('Ready to start simulation?');
    
    // Match starts
    await this.simulateMatchStart();
    
    // Map 1: Team 1 wins 3-2
    await this.simulateHeroPicks(1);
    await this.simulateMapProgress(1, 3, 2);
    await this.simulateMapCompletion(1, 1);
    
    // Map 2: Team 2 wins 3-1  
    await this.simulateHeroPicks(2);
    await this.simulateMapProgress(2, 1, 3);
    await this.simulateMapCompletion(2, 2);
    
    // Map 3: Team 1 wins 3-0 (series decider)
    await this.simulateHeroPicks(3);
    await this.simulateMapProgress(3, 3, 0);
    await this.simulateMapCompletion(3, 1);
    
    // Match completion
    await this.simulateMatchCompletion();
    
    console.log('\n🎯 SIMULATION COMPLETE!');
    console.log('All live updates have been broadcast via localStorage.');
    console.log('In a real scenario, MatchDetailPage would receive and display all these updates instantly.');
    
    rl.close();
  }
}

// Run the simulation
const simulator = new LiveMatchSimulator();
simulator.runFullSimulation().catch(console.error);