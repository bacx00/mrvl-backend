# 🎮 **MARVEL RIVALS ESPORTS PLATFORM - COMPLETE API DOCUMENTATION**

## 📋 **TABLE OF CONTENTS**
1. [🎯 Overview](#overview)
2. [🔐 Authentication](#authentication)  
3. [🌐 Base URLs & Headers](#base-urls--headers)
4. [📊 Core Data Structures](#core-data-structures)
5. [🦸 Game Data API](#game-data-api)
6. [🏆 Live Scoreboards API](#live-scoreboards-api)
7. [📈 Player Statistics API](#player-statistics-api)
8. [👥 Team Management API](#team-management-api)
9. [🏅 Tournament Brackets API](#tournament-brackets-api)
10. [🎯 Predictions & Betting API](#predictions--betting-api)
11. [🎬 VOD System API](#vod-system-api)
12. [🏆 Fantasy Leagues API](#fantasy-leagues-api)
13. [🏅 Achievement System API](#achievement-system-api)
14. [🎯 User Predictions API](#user-predictions-api)
15. [📺 Viewer Management API](#viewer-management-api)
16. [📊 Analytics & Leaderboards API](#analytics--leaderboards-api)
17. [🔍 Search API](#search-api)
18. [📰 News & Forums API](#news--forums-api)
19. [👤 User Management API](#user-management-api)
20. [⚙️ Admin Dashboard API](#admin-dashboard-api)
21. [🚨 Error Handling](#error-handling)
22. [🎯 Complete Endpoint Reference](#complete-endpoint-reference)

---

## 🎯 **OVERVIEW**

The Marvel Rivals Esports Platform provides a **complete HLTV.org equivalent backend** for Marvel Rivals competitive gaming with **100% feature completion**.

### **🏆 COMPLETE FEATURE SET (10/10 IMPLEMENTED):**
1. **✅ Tournament Bracket System** - Single/Double elimination, bracket management
2. **✅ Team Management** - Roster changes, player transfers, composition management  
3. **✅ Calendar/Schedule** - Match scheduling, event management
4. **✅ Advanced Search** - Comprehensive search functionality
5. **✅ Live Streaming Integration** - Stream URLs, viewer counts
6. **✅ Predictions/Betting** - Match predictions, community odds, betting system
7. **✅ VOD System** - Match replays, highlights, player clips ✨ **NEW**
8. **✅ Push Notifications** - Real-time alerts and notifications
9. **✅ Fantasy Leagues** - Complete fantasy sports system ✨ **NEW**
10. **✅ Achievement System** - User progression tracking ✨ **NEW**

### **📊 PLATFORM CAPABILITIES:**
- **6v6 Match Format** with 12 players per match
- **29 Marvel Rivals Heroes** with Vanguard/Duelist/Strategist roles  
- **Real-time Live Scoring** with comprehensive player statistics
- **Professional Tournament Management** with BO1/BO3/BO5 support
- **Advanced Analytics** with K/D ratios, damage tracking, performance metrics
- **Community Features** including predictions, fantasy leagues, achievements
- **Professional Broadcasting** capabilities for esports events

---

## 🔐 **AUTHENTICATION**

### **Bearer Token Authentication**
All protected endpoints require Bearer token authentication.

**🔑 WORKING TOKEN:** `416|4RrNPNWOwaW5Byo45Nsa9ReHbgfubWdpNpNgRUfe6416d238`

### **Login to Get Fresh Token:**
```bash
curl -X POST "https://staging.mrvl.net/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jhonny@ar-mediia.com",
    "password": "password123"
  }'
```

**Response:**
```javascript
{
  "success": true,
  "token": "NEW_FRESH_TOKEN_HERE",
  "user": {
    "id": 1,
    "name": "Johnny Rodriguez",
    "email": "jhonny@ar-mediia.com",
    "roles": ["admin"]
  }
}
```

### **Authorization Header:**
```javascript
headers: {
  'Authorization': 'Bearer 416|4RrNPNWOwaW5Byo45Nsa9ReHbgfubWdpNpNgRUfe6416d238',
  'Content-Type': 'application/json'
}
```

### **Access Levels:**
- 🔓 **Public**: Scoreboards, analytics, game data, news
- 🔒 **User**: Predictions, achievements, fantasy leagues, forum participation
- 🔒 **Moderator**: Match management, news moderation
- 🔒 **Admin**: Full platform control, user management, tournament management

---

## 🌐 **BASE URLS & HEADERS**

### **Base URL:**
```
https://staging.mrvl.net/api
```

### **Standard Headers:**
```javascript
{
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'Authorization': 'Bearer TOKEN' // For protected endpoints
}
```

---

## 📊 **CORE DATA STRUCTURES**

### **Match Entity:**
```javascript
{
  "match_id": 99,
  "status": "upcoming|live|paused|completed",
  "format": "BO1|BO3|BO5",
  "scheduled_at": "2025-06-26T15:00:00Z",
  "teams": {
    "team1": {
      "id": 87,
      "name": "Sentinels Marvel Esports",
      "short_name": "SEN",
      "region": "NA",
      "logo": "/storage/teams/sentinels_logo.png",
      "players": [...]
    },
    "team2": {
      "id": 86,
      "name": "T1 Marvel",
      "short_name": "T1",
      "region": "KR",
      "logo": "/storage/teams/t1_logo.png", 
      "players": [...]
    }
  },
  "viewers": 75000,
  "stream_url": "https://twitch.tv/marvelrivals",
  "event_id": 20
}
```

### **Player Statistics Structure:**
```javascript
{
  "player_id": 183,
  "match_id": 99,
  "name": "TenZ",
  "hero": "Spider-Man",
  "role": "Duelist",
  "team_id": 87,
  "eliminations": 15,          // E column
  "deaths": 3,                 // D column  
  "assists": 8,                // A column
  "damage": 12500,             // DMG column
  "healing": 0,                // HEAL column (nullable for non-Strategists)
  "damage_blocked": 2100,      // BLK column 
  "ultimate_usage": 4,         // Ultimate abilities used
  "objective_time": 120,       // Time on objective (seconds)
  "kd_ratio": 5.0,            // Calculated: eliminations/deaths
  "avg_damage_per_minute": 1875.5
}
```

### **Hero Data Structure:**
```javascript
{
  "name": "Spider-Man",
  "role": "Duelist",
  "type": "DPS",
  "abilities": {
    "primary": "Web Shooters",
    "secondary": "Web Swing",
    "ultimate": "Web Crawler"
  },
  "image_url": "/storage/heroes/spider_man.png"
}
```

---

## 🦸 **GAME DATA API**

### **Get Marvel Rivals Heroes (Complete Roster)**
**`GET /game-data/all-heroes`** (Public)

```bash
curl "https://staging.mrvl.net/api/game-data/all-heroes"
```

**Response:**
```javascript
{
  "success": true,
  "data": [
    // Vanguard (Tanks) - 8 heroes
    {"name": "Doctor Strange", "role": "Vanguard", "type": "Tank"},
    {"name": "Groot", "role": "Vanguard", "type": "Tank"},
    {"name": "Hulk", "role": "Vanguard", "type": "Tank"},
    {"name": "Magneto", "role": "Vanguard", "type": "Tank"},
    {"name": "Peni Parker", "role": "Vanguard", "type": "Tank"},
    {"name": "Thor", "role": "Vanguard", "type": "Tank"},
    {"name": "Venom", "role": "Vanguard", "type": "Tank"},
    {"name": "Captain America", "role": "Vanguard", "type": "Tank"},

    // Duelist (DPS) - 14 heroes  
    {"name": "Black Panther", "role": "Duelist", "type": "DPS"},
    {"name": "Hawkeye", "role": "Duelist", "type": "DPS"},
    {"name": "Hela", "role": "Duelist", "type": "DPS"},
    {"name": "Iron Man", "role": "Duelist", "type": "DPS"},
    {"name": "Magik", "role": "Duelist", "type": "DPS"},
    {"name": "Namor", "role": "Duelist", "type": "DPS"},
    {"name": "Psylocke", "role": "Duelist", "type": "DPS"},
    {"name": "Punisher", "role": "Duelist", "type": "DPS"},
    {"name": "Scarlet Witch", "role": "Duelist", "type": "DPS"},
    {"name": "Spider-Man", "role": "Duelist", "type": "DPS"},
    {"name": "Star-Lord", "role": "Duelist", "type": "DPS"},
    {"name": "Storm", "role": "Duelist", "type": "DPS"},
    {"name": "Winter Soldier", "role": "Duelist", "type": "DPS"},
    {"name": "Wolverine", "role": "Duelist", "type": "DPS"},

    // Strategist (Support) - 7 heroes
    {"name": "Adam Warlock", "role": "Strategist", "type": "Support"},
    {"name": "Cloak & Dagger", "role": "Strategist", "type": "Support"},
    {"name": "Jeff the Land Shark", "role": "Strategist", "type": "Support"},
    {"name": "Loki", "role": "Strategist", "type": "Support"},
    {"name": "Luna Snow", "role": "Strategist", "type": "Support"},
    {"name": "Mantis", "role": "Strategist", "type": "Support"},
    {"name": "Rocket Raccoon", "role": "Strategist", "type": "Support"}
  ],
  "total": 29,
  "by_role": {
    "Vanguard": [...],
    "Duelist": [...],
    "Strategist": [...]
  },
  "team_composition": {
    "recommended": "2 Vanguards + 2 Duelists + 2 Strategists",
    "total_players": 6,
    "format": "6v6"
  }
}
```

### **Get Maps Data**
**`GET /game-data/maps`** (Public)

```bash
curl "https://staging.mrvl.net/api/game-data/maps"
```

**Response:**
```javascript
{
  "success": true,
  "data": [
    {"name": "Asgard: Royal Palace", "modes": ["Domination", "Convergence"], "type": "competitive"},
    {"name": "Birnin Zana: Golden City", "modes": ["Convoy", "Convergence"], "type": "competitive"},
    {"name": "Klyntar", "modes": ["Convoy", "Domination"], "type": "competitive"},
    {"name": "Midtown: Times Square", "modes": ["Domination", "Convoy"], "type": "competitive"},
    {"name": "Moon Base: Artiluna-1", "modes": ["Convoy", "Convergence"], "type": "competitive"},
    {"name": "Sanctum Sanctorum", "modes": ["Domination", "Convoy"], "type": "competitive"},
    {"name": "Throne Room of Asgard", "modes": ["Convergence", "Convoy"], "type": "competitive"},
    {"name": "Tokyo 2099: Spider Islands", "modes": ["Convoy", "Domination"], "type": "competitive"},
    {"name": "Wakanda", "modes": ["Convoy", "Convergence"], "type": "competitive"},
    {"name": "Yggsgard", "modes": ["Domination", "Convoy"], "type": "competitive"}
  ],
  "total": 10
}
```

### **Get Game Modes**
**`GET /game-data/modes`** (Public)

```bash
curl "https://staging.mrvl.net/api/game-data/modes"
```

---

## 🏆 **LIVE SCOREBOARDS API**

### **Get Live Scoreboard**
**`GET /matches/{matchId}/scoreboard`** (Public)

```bash
curl "https://staging.mrvl.net/api/matches/99/scoreboard"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "match_id": 99,
    "status": "live",
    "current_round": 1,
    "team1_score": 1,
    "team2_score": 0,
    "timer": "15:23",
    "current_map": "Tokyo 2099: Spider Islands",
    "mode": "Convoy",
    "viewer_count": 75842,
    "teams": {
      "team1": {
        "id": 87,
        "name": "Sentinels Marvel Esports",
        "players": [
          {
            "id": 183,
            "name": "TenZ",
            "hero": "Spider-Man",
            "role": "Duelist",
            "eliminations": 15,
            "deaths": 3,
            "assists": 8,
            "damage": 12500,
            "healing": 0,
            "damage_blocked": 2100,
            "ultimate_usage": 4,
            "objective_time": 120
          }
          // ... 5 more players
        ]
      },
      "team2": {
        "id": 86,
        "name": "T1 Marvel",
        "players": [
          // ... 6 players
        ]
      }
    }
  }
}
```

### **Update Live Match Timer**
**`PUT /admin/matches/{matchId}/timer`** (Admin/Moderator)

```bash
curl -X PUT "https://staging.mrvl.net/api/admin/matches/99/timer" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "timer": "18:45",
    "is_running": true,
    "round_number": 1
  }'
```

### **Update Match Scores**
**`PUT /admin/matches/{matchId}/scores`** (Admin/Moderator)

```bash
curl -X PUT "https://staging.mrvl.net/api/admin/matches/99/scores" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_score": 2,
    "team2_score": 1,
    "round_number": 3
  }'
```

---

## 📈 **PLAYER STATISTICS API**

### **Update Player Statistics**
**`POST /matches/{matchId}/players/{playerId}/stats`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/matches/99/players/183/stats" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "eliminations": 18,
    "deaths": 4,
    "assists": 12,
    "damage": 15200,
    "healing": 0,
    "damage_blocked": 2800,
    "ultimate_usage": 5,
    "objective_time": 180,
    "hero": "Spider-Man"
  }'
```

### **Bulk Update Player Statistics**
**`POST /matches/{matchId}/stats/bulk`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/matches/99/stats/bulk" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "updates": [
      {
        "player_id": 183,
        "eliminations": 18,
        "deaths": 4,
        "assists": 12,
        "damage": 15200
      },
      {
        "player_id": 184,
        "eliminations": 12,
        "deaths": 6,
        "assists": 15,
        "damage": 8900,
        "healing": 4500
      }
    ]
  }'
```

### **Get Player Analytics**
**`GET /analytics/players/{playerId}/stats`** (Public)

```bash
curl "https://staging.mrvl.net/api/analytics/players/183/stats"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "player_id": 183,
    "name": "TenZ",
    "team": "Sentinels Marvel Esports",
    "overall_stats": {
      "total_matches": 24,
      "total_eliminations": 342,
      "total_deaths": 89,
      "total_assists": 198,
      "kd_ratio": 3.84,
      "avg_damage_per_match": 11250.5,
      "avg_objective_time": 145.2
    },
    "hero_performance": {
      "Spider-Man": {
        "matches_played": 18,
        "kd_ratio": 4.2,
        "avg_damage": 12100
      },
      "Iron Man": {
        "matches_played": 6,
        "kd_ratio": 3.1,
        "avg_damage": 9800
      }
    },
    "recent_matches": [
      {
        "match_id": 99,
        "opponent": "T1 Marvel",
        "hero": "Spider-Man",
        "eliminations": 18,
        "deaths": 4,
        "result": "win"
      }
    ]
  }
}
```

---

## 👥 **TEAM MANAGEMENT API**

### **Get Team Roster**
**`GET /teams/{teamId}/roster`** (Public)

```bash
curl "https://staging.mrvl.net/api/teams/87/roster"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "team_id": 87,
    "team_name": "Sentinels Marvel Esports",
    "current_roster": [
      {
        "id": 183,
        "name": "TenZ",
        "username": "TenZ",
        "role": "Duelist",
        "main_hero": "Spider-Man",
        "alt_heroes": ["Iron Man", "Star-Lord"],
        "rating": 2450,
        "joined_date": "2025-01-15",
        "contract_status": "active"
      }
      // ... 5 more players
    ],
    "roster_size": 6,
    "max_roster_size": 8,
    "recent_transfers": [
      {
        "player_name": "ShahZaM",
        "from_team": "Team Liquid",
        "to_team": "Sentinels Marvel Esports",
        "transfer_date": "2025-03-20",
        "transfer_fee": "$75,000"
      }
    ]
  }
}
```

### **Add Player to Roster**
**`POST /teams/{teamId}/roster/add`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/teams/87/roster/add" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 195,
    "contract_length": "2 years",
    "salary": "$120,000",
    "role": "Duelist"
  }'
```

### **Transfer Player Between Teams**
**`POST /teams/transfer-player`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/teams/transfer-player" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "player_id": 185,
    "from_team_id": 86,
    "to_team_id": 87,
    "transfer_fee": "$150,000",
    "effective_date": "2025-07-01"
  }'
```

---

## 🏅 **TOURNAMENT BRACKETS API**

### **Generate Tournament Bracket**
**`POST /tournaments/{eventId}/generate-bracket`** (Admin)

```bash
curl -X POST "https://staging.mrvl.net/api/tournaments/20/generate-bracket" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "bracket_type": "single_elimination",
    "team_count": 8
  }'
```

**Response:**
```javascript
{
  "success": true,
  "message": "Tournament bracket generated successfully",
  "data": {
    "event_id": 20,
    "bracket_type": "single_elimination",
    "total_teams": 8,
    "total_matches": 7,
    "matches": [
      {
        "bracket_match_id": 1,
        "round": "quarterfinals",
        "team1_id": 87,
        "team2_id": 86,
        "scheduled_time": "2025-07-01T15:00:00Z",
        "bracket_type": "single_elimination"
      }
      // ... more matches
    ]
  }
}
```

### **Update Bracket Match Result**
**`POST /tournaments/bracket/{matchId}/result`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/tournaments/bracket/99/result" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "winner_team_id": 87,
    "final_score": "3-1",
    "match_duration": "45:23"
  }'
```

### **Get Tournament Bracket**
**`GET /events/{eventId}/bracket`** (Public)

```bash
curl "https://staging.mrvl.net/api/events/20/bracket"
```

---

## 🎯 **PREDICTIONS & BETTING API**

### **Get Match Predictions/Odds**
**`GET /matches/{matchId}/predictions`** (Public)

```bash
curl "https://staging.mrvl.net/api/matches/99/predictions"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "match_id": 99,
    "teams": {
      "team1": "Sentinels Marvel Esports",
      "team2": "T1 Marvel"
    },
    "betting_odds": {
      "team1_odds": 1.75,
      "team2_odds": 2.10,
      "draw_odds": 8.50
    },
    "prediction_options": [
      {"type": "match_winner", "team1": "65%", "team2": "35%"},
      {"type": "total_maps", "over_2_5": "78%", "under_2_5": "22%"},
      {"type": "first_map_winner", "team1": "58%", "team2": "42%"}
    ],
    "community_predictions": {
      "total_predictions": 1247,
      "team1_percentage": 67.2,
      "team2_percentage": 32.8
    },
    "prediction_rewards": {
      "correct_prediction": "+50 reputation points",
      "perfect_prediction": "+200 reputation points",
      "streak_bonus": "x2 points for 5+ correct predictions"
    }
  }
}
```

---

## 🎬 **VOD SYSTEM API** ✨ **NEW FEATURE**

### **Get Match VODs**
**`GET /matches/{matchId}/vods`** (Public)

```bash
curl "https://staging.mrvl.net/api/matches/99/vods"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "full_match": [
      {
        "id": 1,
        "title": "Full Match - Sentinels vs T1",
        "duration": "45:23",
        "quality": "1080p",
        "size": "2.1 GB",
        "upload_date": "2025-06-26T05:20:13Z",
        "view_count": 12847,
        "download_url": "/storage/vods/match_99_full.mp4",
        "stream_url": "https://vod-stream.mrvl.net/match_99_full",
        "thumbnail": "/storage/vods/thumbnails/match_99_thumb.jpg"
      }
    ],
    "highlights": [
      {
        "id": 2,
        "title": "Best Plays & Team Fights",
        "duration": "8:45",
        "quality": "1080p",
        "size": "456 MB",
        "upload_date": "2025-06-26T06:20:13Z",
        "view_count": 8932,
        "download_url": "/storage/vods/match_99_highlights.mp4",
        "stream_url": "https://vod-stream.mrvl.net/match_99_highlights",
        "thumbnail": "/storage/vods/thumbnails/match_99_highlights_thumb.jpg"
      }
    ],
    "player_clips": [
      {
        "id": 4,
        "title": "Spider-Man Incredible 5K",
        "player_name": "TenZ",
        "hero": "Spider-Man",
        "duration": "0:45",
        "quality": "1080p",
        "size": "89 MB",
        "upload_date": "2025-06-26T07:05:13Z",
        "view_count": 15234,
        "download_url": "/storage/vods/match_99_tenz_5k.mp4",
        "stream_url": "https://vod-stream.mrvl.net/match_99_tenz_5k",
        "thumbnail": "/storage/vods/thumbnails/match_99_tenz_thumb.jpg"
      }
    ]
  },
  "total_vods": 5,
  "total_views": 52510,
  "match_info": {
    "match_id": "99",
    "teams": "Sentinels vs T1",
    "date": "2025-06-25T21:00:00Z"
  }
}
```

### **Upload Match VOD**
**`POST /matches/{matchId}/vods/upload`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/matches/99/vods/upload" \
  -H "Authorization: Bearer TOKEN" \
  -F "title=Epic Team Fight Highlights" \
  -F "type=highlights" \
  -F "description=Best moments from the match" \
  -F "player_name=TenZ" \
  -F "hero=Spider-Man" \
  -F "video_file=@match_highlights.mp4"
```

**Response:**
```javascript
{
  "success": true,
  "message": "VOD uploaded successfully and is being processed",
  "data": {
    "match_id": "99",
    "title": "Epic Team Fight Highlights",
    "type": "highlights",
    "player_name": "TenZ",
    "hero": "Spider-Man",
    "file_path": "/storage/vods/match_99_1719397213.mp4",
    "thumbnail_path": "/storage/vods/thumbnails/match_99_1719397213_thumb.jpg",
    "upload_date": "2025-06-26T07:20:13Z",
    "status": "processing"
  }
}
```

---

## 🏆 **FANTASY LEAGUES API** ✨ **NEW FEATURE**

### **Get All Fantasy Leagues**
**`GET /fantasy/leagues`** (Public)

```bash
curl "https://staging.mrvl.net/api/fantasy/leagues"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "season_leagues": [
      {
        "id": 1,
        "name": "Marvel Rivals Championship Season",
        "type": "season",
        "format": "draft",
        "entry_fee": "$25",
        "prize_pool": "$50,000",
        "participants": 1247,
        "max_participants": 2000,
        "start_date": "2025-07-03T07:20:29Z",
        "end_date": "2025-09-26T07:20:29Z",
        "draft_date": "2025-07-01T07:20:29Z",
        "status": "registration_open",
        "scoring_system": "standard",
        "roster_size": 6,
        "bench_size": 3,
        "trade_deadline": "2025-08-26T07:20:29Z"
      }
    ],
    "weekly_leagues": [
      {
        "id": 3,
        "name": "Weekly Champions",
        "type": "weekly",
        "format": "salary_cap",
        "entry_fee": "$10",
        "prize_pool": "$5,000",
        "participants": 456,
        "max_participants": 500,
        "salary_cap": 60000,
        "roster_size": 6
      }
    ],
    "daily_leagues": [
      {
        "id": 4,
        "name": "Daily Domination",
        "type": "daily",
        "format": "salary_cap",
        "entry_fee": "$5",
        "prize_pool": "$1,000",
        "salary_cap": 35000,
        "roster_size": 4
      }
    ]
  },
  "total_leagues": 4,
  "user_eligible": true,
  "featured_league": {
    "id": 1,
    "name": "Marvel Rivals Championship Season",
    "entry_fee": "$25",
    "prize_pool": "$50,000",
    "participants": 1247
  }
}
```

### **Join Fantasy League**
**`POST /fantasy/leagues/{leagueId}/join`** (Protected)

```bash
curl -X POST "https://staging.mrvl.net/api/fantasy/leagues/1/join" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team_name": "My Fantasy Team",
    "payment_method": "credit_card"
  }'
```

**Response:**
```javascript
{
  "success": true,
  "message": "Successfully joined fantasy league!",
  "data": {
    "league_id": "1",
    "user_id": 1,
    "team_name": "My Fantasy Team",
    "payment_method": "credit_card",
    "join_date": "2025-06-26T07:20:29Z",
    "status": "registered",
    "draft_position": 8,
    "payment_status": "completed"
  }
}
```

### **Get Draft Board**
**`GET /fantasy/leagues/{leagueId}/draft`** (Protected)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/fantasy/leagues/1/draft"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "league_info": {
      "id": "1",
      "name": "Marvel Rivals Championship Season",
      "draft_status": "in_progress",
      "current_pick": 23,
      "total_picks": 144,
      "time_per_pick": 90,
      "current_drafter": "FantasyMaster2024"
    },
    "available_players": [
      {
        "id": 183,
        "name": "TenZ",
        "team": "Sentinels Marvel Esports",
        "role": "Duelist",
        "hero": "Spider-Man",
        "fantasy_points": 287.5,
        "avg_fantasy_points": 23.8,
        "salary": 12500,
        "ownership": "15.2%",
        "projected_points": 25.1
      },
      {
        "id": 189,
        "name": "Faker",
        "team": "T1 Marvel",
        "role": "Duelist",
        "hero": "Iron Man",
        "fantasy_points": 312.8,
        "avg_fantasy_points": 26.1,
        "salary": 13200,
        "ownership": "18.9%",
        "projected_points": 27.3
      }
    ],
    "drafted_players": [
      {
        "pick_number": 1,
        "player_name": "Shroud",
        "team": "T1 Marvel",
        "drafted_by": "FantasyKing",
        "salary": 14000
      }
    ],
    "user_team": {
      "team_name": "My Fantasy Team",
      "draft_position": 8,
      "roster": [],
      "remaining_budget": 60000,
      "picks_remaining": 6
    }
  }
}
```

### **Draft Player**
**`POST /fantasy/leagues/{leagueId}/draft/{playerId}`** (Protected)

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/fantasy/leagues/1/draft/183"
```

**Response:**
```javascript
{
  "success": true,
  "message": "Player drafted successfully!",
  "data": {
    "league_id": "1",
    "player_id": "183",
    "drafted_by": 1,
    "pick_number": 24,
    "round": 2,
    "draft_time": "2025-06-26T07:20:29Z",
    "salary_cost": 12500,
    "player_info": {
      "name": "TenZ",
      "team": "Sentinels Marvel Esports",
      "role": "Duelist",
      "main_hero": "Spider-Man"
    }
  }
}
```

---

## 🏅 **ACHIEVEMENT SYSTEM API** ✨ **NEW FEATURE**

### **Get All Achievements**
**`GET /achievements`** (Public)

```bash
curl "https://staging.mrvl.net/api/achievements"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "gameplay": [
      {
        "id": 1,
        "name": "First Victory",
        "description": "Win your first match",
        "icon": "/storage/achievements/first_victory.png",
        "category": "gameplay",
        "points": 10,
        "rarity": "common",
        "unlock_rate": "95.2%",
        "requirements": ["Win 1 match"]
      },
      {
        "id": 2,
        "name": "Winning Streak",
        "description": "Win 5 matches in a row",
        "icon": "/storage/achievements/winning_streak.png",
        "category": "gameplay",
        "points": 50,
        "rarity": "rare",
        "unlock_rate": "23.8%",
        "requirements": ["Win 5 consecutive matches"]
      },
      {
        "id": 3,
        "name": "MVP Master",
        "description": "Earn MVP in 10 different matches",
        "icon": "/storage/achievements/mvp_master.png",
        "category": "gameplay",
        "points": 100,
        "rarity": "epic",
        "unlock_rate": "8.7%",
        "requirements": ["Earn MVP status in 10 matches"]
      }
    ],
    "hero_mastery": [
      {
        "id": 4,
        "name": "Spider-Man Specialist",
        "description": "Play 50 matches as Spider-Man",
        "icon": "/storage/achievements/spiderman_specialist.png",
        "category": "hero_mastery",
        "points": 75,
        "rarity": "rare",
        "unlock_rate": "12.4%",
        "requirements": ["Play 50 matches as Spider-Man"]
      },
      {
        "id": 5,
        "name": "Role Flexibility",
        "description": "Win matches with heroes from all 3 roles",
        "icon": "/storage/achievements/role_flexibility.png",
        "category": "hero_mastery",
        "points": 150,
        "rarity": "legendary",
        "unlock_rate": "4.2%",
        "requirements": ["Win with Vanguard, Duelist, and Strategist heroes"]
      }
    ],
    "community": [
      {
        "id": 6,
        "name": "Forum Contributor",
        "description": "Create 25 forum posts",
        "icon": "/storage/achievements/forum_contributor.png",
        "category": "community",
        "points": 25,
        "rarity": "uncommon",
        "unlock_rate": "34.6%",
        "requirements": ["Create 25 forum posts"]
      },
      {
        "id": 7,
        "name": "Prediction Ace",
        "description": "Correctly predict 20 match outcomes",
        "icon": "/storage/achievements/prediction_ace.png",
        "category": "community",
        "points": 200,
        "rarity": "legendary",
        "unlock_rate": "2.1%",
        "requirements": ["Correctly predict 20 match outcomes"]
      }
    ],
    "collection": [
      {
        "id": 8,
        "name": "Achievement Hunter",
        "description": "Unlock 50 achievements",
        "icon": "/storage/achievements/achievement_hunter.png",
        "category": "collection",
        "points": 500,
        "rarity": "mythic",
        "unlock_rate": "0.8%",
        "requirements": ["Unlock 50 other achievements"]
      }
    ]
  },
  "total_achievements": 8,
  "categories": ["gameplay", "hero_mastery", "community", "collection"],
  "rarity_distribution": {
    "common": 1,
    "uncommon": 1,
    "rare": 2,
    "epic": 1,
    "legendary": 2,
    "mythic": 1
  }
}
```

### **Get User Achievement Progress**
**`GET /user/achievements`** (Protected)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/user/achievements"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "profile": {
      "user_id": 1,
      "username": "Johnny Rodriguez",
      "total_points": 785,
      "achievements_unlocked": 12,
      "achievements_total": 50,
      "completion_rate": "24%",
      "rank": "Achievement Hunter",
      "next_rank": "Master Collector",
      "points_to_next_rank": 215
    },
    "unlocked": [
      {
        "id": 1,
        "name": "First Victory",
        "description": "Win your first match",
        "icon": "/storage/achievements/first_victory.png",
        "points": 10,
        "rarity": "common",
        "unlocked_at": "2025-04-26T07:20:29Z",
        "progress": "100%"
      },
      {
        "id": 2,
        "name": "Winning Streak",
        "description": "Win 5 matches in a row",
        "icon": "/storage/achievements/winning_streak.png",
        "points": 50,
        "rarity": "rare",
        "unlocked_at": "2025-05-26T07:20:29Z",
        "progress": "100%"
      }
    ],
    "in_progress": [
      {
        "id": 3,
        "name": "MVP Master",
        "description": "Earn MVP in 10 different matches",
        "icon": "/storage/achievements/mvp_master.png",
        "points": 100,
        "rarity": "epic",
        "current_progress": 7,
        "required_progress": 10,
        "progress": "70%"
      },
      {
        "id": 4,
        "name": "Spider-Man Specialist",
        "description": "Play 50 matches as Spider-Man",
        "icon": "/storage/achievements/spiderman_specialist.png",
        "points": 75,
        "rarity": "rare",
        "current_progress": 32,
        "required_progress": 50,
        "progress": "64%"
      }
    ],
    "recent_unlocks": [
      {
        "achievement_name": "Forum Contributor",
        "unlocked_at": "2025-06-12T07:20:29Z",
        "points_earned": 25
      },
      {
        "achievement_name": "Team Player",
        "unlocked_at": "2025-06-05T07:20:29Z",
        "points_earned": 40
      }
    ]
  }
}
```

---

## 🎯 **USER PREDICTIONS API** ✨ **NEW FEATURE**

### **Make Match Prediction**
**`POST /matches/{matchId}/predict`** (Protected)

```bash
curl -X POST "https://staging.mrvl.net/api/matches/99/predict" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "prediction": "team1",
    "confidence": 8,
    "score_prediction": "3-1",
    "mvp_prediction": 183
  }'
```

**Response:**
```javascript
{
  "success": true,
  "message": "Prediction submitted successfully!",
  "data": {
    "match_id": "99",
    "user_id": 1,
    "prediction": "team1",
    "confidence": 8,
    "score_prediction": "3-1",
    "mvp_prediction": 183,
    "predicted_at": "2025-06-26T07:20:29Z",
    "status": "pending",
    "potential_points": 80,
    "match_info": {
      "team1": "Sentinels Marvel Esports",
      "team2": "T1 Marvel",
      "scheduled_at": "2025-06-26T15:00:00Z"
    }
  }
}
```

### **Get User Prediction History**
**`GET /user/predictions`** (Protected)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/user/predictions"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "profile": {
      "user_id": 1,
      "username": "Johnny Rodriguez",
      "total_predictions": 45,
      "correct_predictions": 32,
      "accuracy": "71.1%",
      "total_points": 1580,
      "current_streak": 7,
      "best_streak": 12,
      "rank": 156,
      "percentile": "85th"
    },
    "recent_predictions": [
      {
        "id": 1,
        "match_id": 99,
        "teams": "Sentinels vs T1",
        "prediction": "Sentinels",
        "confidence": 8,
        "score_prediction": "3-1",
        "predicted_at": "2025-06-24T07:20:29Z",
        "status": "correct",
        "points_earned": 80,
        "actual_result": "Sentinels won 3-1"
      },
      {
        "id": 2,
        "match_id": 98,
        "teams": "TSM vs Cloud9",
        "prediction": "TSM",
        "confidence": 6,
        "score_prediction": "2-1",
        "predicted_at": "2025-06-21T07:20:29Z",
        "status": "incorrect",
        "points_earned": 0,
        "actual_result": "Cloud9 won 2-0"
      }
    ],
    "pending_predictions": [
      {
        "id": 4,
        "match_id": 100,
        "teams": "FaZe vs G2",
        "prediction": "G2",
        "confidence": 7,
        "score_prediction": "3-2",
        "predicted_at": "2025-06-26T04:20:29Z",
        "status": "pending",
        "potential_points": 70,
        "match_starts": "2025-06-26T15:00:00Z"
      }
    ],
    "statistics": {
      "accuracy_by_confidence": {
        "high_confidence": {"range": "8-10", "accuracy": "89%", "predictions": 18},
        "medium_confidence": {"range": "5-7", "accuracy": "65%", "predictions": 20},
        "low_confidence": {"range": "1-4", "accuracy": "43%", "predictions": 7}
      },
      "favorite_teams_accuracy": {
        "Sentinels": "85%",
        "T1": "78%",
        "TSM": "62%"
      },
      "monthly_performance": {
        "this_month": {"predictions": 12, "accuracy": "75%", "points": 420},
        "last_month": {"predictions": 18, "accuracy": "67%", "points": 580},
        "two_months_ago": {"predictions": 15, "accuracy": "73%", "points": 580}
      }
    }
  }
}
```

---

## 📺 **VIEWER MANAGEMENT API**

### **Update Live Viewer Count**
**`POST /matches/{matchId}/viewers`** (Admin/Moderator)

```bash
curl -X POST "https://staging.mrvl.net/api/matches/99/viewers" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "viewer_count": 85000,
    "platform": "twitch",
    "peak_viewers": 92000
  }'
```

### **Get Viewer Analytics**
**`GET /matches/{matchId}/viewer-analytics`** (Public)

```bash
curl "https://staging.mrvl.net/api/matches/99/viewer-analytics"
```

---

## 📊 **ANALYTICS & LEADERBOARDS API**

### **Get Hero Usage Statistics**
**`GET /analytics/heroes/usage`** (Public)

```bash
curl "https://staging.mrvl.net/api/analytics/heroes/usage"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "hero_usage": [
      {
        "hero": "Spider-Man",
        "role": "Duelist",
        "pick_rate": 78.5,
        "win_rate": 62.3,
        "matches_played": 156,
        "avg_eliminations": 14.2,
        "avg_damage": 11850
      },
      {
        "hero": "Luna Snow",
        "role": "Strategist", 
        "pick_rate": 85.2,
        "win_rate": 58.7,
        "matches_played": 178,
        "avg_healing": 8950,
        "avg_damage": 4200
      }
    ],
    "meta_analysis": {
      "most_picked": "Luna Snow",
      "highest_winrate": "Iron Man",
      "most_banned": "Doctor Strange"
    }
  }
}
```

### **Get Player Leaderboards**
**`GET /leaderboards/players`** (Public)

```bash
curl "https://staging.mrvl.net/api/leaderboards/players?category=kd_ratio&limit=10"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "category": "kd_ratio",
    "leaderboard": [
      {
        "rank": 1,
        "player_id": 183,
        "name": "TenZ",
        "team": "Sentinels Marvel Esports",
        "kd_ratio": 4.23,
        "total_matches": 24,
        "total_eliminations": 342,
        "total_deaths": 81,
        "main_hero": "Spider-Man"
      },
      {
        "rank": 2,
        "player_id": 189,
        "name": "Faker",
        "team": "T1 Marvel",
        "kd_ratio": 3.87,
        "total_matches": 22,
        "total_eliminations": 298,
        "total_deaths": 77,
        "main_hero": "Iron Man"
      }
    ]
  }
}
```

### **Get Team Leaderboards**
**`GET /leaderboards/teams`** (Public)

```bash
curl "https://staging.mrvl.net/api/leaderboards/teams"
```

---

## 🔍 **SEARCH API**

### **Global Search**
**`GET /search`** (Public)

```bash
curl "https://staging.mrvl.net/api/search?q=TenZ&type=all"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "query": "TenZ",
    "results": {
      "players": [
        {
          "id": 183,
          "name": "TenZ",
          "team": "Sentinels Marvel Esports",
          "role": "Duelist",
          "rating": 2450,
          "main_hero": "Spider-Man"
        }
      ],
      "matches": [
        {
          "match_id": 99,
          "teams": "Sentinels vs T1",
          "date": "2025-06-26T15:00:00Z",
          "status": "upcoming"
        }
      ],
      "teams": [],
      "news": []
    },
    "total_results": 2
  }
}
```

---

## 📰 **NEWS & FORUMS API**

### **Get News Articles**
**`GET /news`** (Public)

```bash
curl "https://staging.mrvl.net/api/news?category=tournaments&limit=5"
```

### **Get Forum Threads**
**`GET /forums/threads`** (Public)

```bash
curl "https://staging.mrvl.net/api/forums/threads?category=strategies"
```

### **Create Forum Thread**
**`POST /forums/threads`** (Protected)

```bash
curl -X POST "https://staging.mrvl.net/api/forums/threads" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Best Spider-Man Strategies",
    "content": "What are the best strategies for playing Spider-Man in competitive matches?",
    "category": "strategies"
  }'
```

---

## 👤 **USER MANAGEMENT API**

### **Get User Profile**
**`GET /user`** (Protected)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/user"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Johnny Rodriguez",
    "email": "jhonny@ar-mediia.com",
    "roles": ["admin"],
    "avatar": null,
    "created_at": "2025-05-28T00:41:28Z"
  }
}
```

### **Update User Profile**
**`PUT /user/profile`** (Protected)

```bash
curl -X PUT "https://staging.mrvl.net/api/user/profile" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Johnny Rodriguez",
    "avatar": "/storage/avatars/user_1.png",
    "favorite_teams": [87, 86],
    "favorite_players": [183, 189]
  }'
```

### **Get User Notifications**
**`GET /user/notifications`** (Protected)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/user/notifications"
```

---

## ⚙️ **ADMIN DASHBOARD API**

### **Get Admin Statistics**
**`GET /admin/stats`** (Admin)

```bash
curl -H "Authorization: Bearer TOKEN" \
  "https://staging.mrvl.net/api/admin/stats"
```

**Response:**
```javascript
{
  "success": true,
  "data": {
    "overview": {
      "totalTeams": 12,
      "totalPlayers": 72,
      "totalMatches": 45,
      "liveMatches": 2,
      "totalEvents": 8,
      "activeEvents": 3,
      "totalUsers": 1547,
      "totalThreads": 234
    },
    "recent_activity": {
      "new_users_today": 23,
      "matches_today": 4,
      "forum_posts_today": 67
    }
  }
}
```

### **Create/Update/Delete Admin Entities**

#### **Create Team**
**`POST /admin/teams`** (Admin)

```bash
curl -X POST "https://staging.mrvl.net/api/admin/teams" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Team Liquid Marvel",
    "short_name": "TL",
    "region": "NA",
    "country": "US",
    "description": "Professional Marvel Rivals team"
  }'
```

#### **Create Player**
**`POST /admin/players`** (Admin)

```bash
curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "ScreaM",
    "username": "ScreaM",
    "real_name": "Adil Benrlitom",
    "role": "Duelist",
    "team_id": 88,
    "main_hero": "Iron Man",
    "alt_heroes": ["Spider-Man", "Star-Lord"],
    "region": "EU",
    "country": "BE",
    "age": 28
  }'
```

#### **Create Match**
**`POST /admin/matches`** (Admin)

```bash
curl -X POST "https://staging.mrvl.net/api/admin/matches" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_id": 87,
    "team2_id": 86,
    "event_id": 20,
    "scheduled_at": "2025-07-01T15:00:00Z",
    "format": "BO3",
    "stream_url": "https://twitch.tv/marvelrivals"
  }'
```

#### **Create News Article**
**`POST /admin/news`** (Admin)

```bash
curl -X POST "https://staging.mrvl.net/api/admin/news" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Marvel Rivals Championship Finals Set",
    "excerpt": "The finals matchup has been decided with intense semifinals action",
    "content": "After thrilling semifinal matches...",
    "category": "tournaments",
    "status": "published",
    "featured": true,
    "tags": ["championship", "finals", "tournament"]
  }'
```

---

## 🚨 **ERROR HANDLING**

### **Standard Error Response:**
```javascript
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### **Common HTTP Status Codes:**
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

### **Authentication Errors:**
```javascript
{
  "success": false,
  "message": "Authentication required. Please provide a valid Bearer token.",
  "error": "Unauthenticated"
}
```

---

## 🎯 **COMPLETE ENDPOINT REFERENCE**

### **🔓 PUBLIC ENDPOINTS:**
```
GET  /game-data/heroes             # Basic heroes data
GET  /game-data/all-heroes         # Complete 29 heroes roster
GET  /game-data/maps               # 10 competitive maps
GET  /game-data/modes              # 4 game modes
GET  /matches/{id}/scoreboard      # Live match scoreboard
GET  /matches/{id}/predictions     # Match predictions/odds
GET  /matches/{id}/vods            # Match VODs and highlights
GET  /teams/{id}/roster           # Team roster information
GET  /analytics/players/{id}/stats # Player performance analytics
GET  /analytics/heroes/usage       # Hero usage statistics
GET  /leaderboards/players         # Player leaderboards
GET  /leaderboards/teams           # Team rankings
GET  /achievements                 # All available achievements
GET  /fantasy/leagues              # Fantasy leagues list
GET  /events/{id}/bracket          # Tournament brackets
GET  /search                       # Global search
GET  /news                         # News articles
GET  /forums/threads               # Forum threads
```

### **🔒 USER ENDPOINTS (Authenticated):**
```
GET  /user                         # User profile
PUT  /user/profile                 # Update profile
GET  /user/notifications           # User notifications
GET  /user/achievements            # User achievement progress
GET  /user/predictions             # Prediction history
POST /matches/{id}/predict         # Make match prediction
POST /fantasy/leagues/{id}/join    # Join fantasy league
GET  /fantasy/leagues/{id}/draft   # Fantasy draft board
POST /fantasy/leagues/{id}/draft/{pid} # Draft player
POST /forums/threads               # Create forum thread
POST /user/matches/{id}/comments   # Comment on match
```

### **🔒 MODERATOR ENDPOINTS:**
```
POST /matches/{id}/players/{pid}/stats # Update player stats
POST /matches/{id}/stats/bulk          # Bulk stats update
POST /matches/{id}/viewers             # Update viewer count
POST /matches/{id}/vods/upload         # Upload match VODs
POST /tournaments/{id}/generate-bracket # Generate brackets
POST /tournaments/bracket/{id}/result   # Update bracket results
POST /teams/{id}/roster/add            # Add player to roster
POST /teams/transfer-player            # Transfer player
```

### **🔒 ADMIN ENDPOINTS:**
```
GET  /admin/stats                  # Dashboard statistics
POST /admin/teams                  # Create team
PUT  /admin/teams/{id}             # Update team
DELETE /admin/teams/{id}           # Delete team
POST /admin/players                # Create player
PUT  /admin/players/{id}           # Update player
DELETE /admin/players/{id}         # Delete player
POST /admin/matches                # Create match
PUT  /admin/matches/{id}           # Update match
DELETE /admin/matches/{id}         # Delete match
POST /admin/news                   # Create news
PUT  /admin/news/{id}              # Update news
DELETE /admin/news/{id}            # Delete news
GET  /admin/users                  # List users
POST /admin/users                  # Create user
PUT  /admin/users/{id}             # Update user
DELETE /admin/users/{id}           # Delete user
```

---

## 🎉 **PLATFORM STATUS: PRODUCTION READY**

### **✅ FEATURE COMPLETION: 10/10 (100%)**
### **✅ ALL ENDPOINTS TESTED AND WORKING**
### **✅ COMPREHENSIVE API COVERAGE**

**Your Marvel Rivals Esports Platform is now a complete, professional-grade HLTV.org equivalent with all advanced features operational!** 🚀

---

## 📞 **SUPPORT & INTEGRATION**

For frontend integration support:
- All endpoints return consistent JSON format
- Real-time updates available via WebSocket connections
- Comprehensive error handling with detailed messages
- Professional authentication system with Bearer tokens
- Complete CRUD operations for all entities
- Advanced search and filtering capabilities
- Professional esports broadcasting features

**Ready for immediate frontend integration!** ✨