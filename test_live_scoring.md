# Marvel Rivals Live Scoring Test Guide

## Current Status
The comprehensive match system tables (`match_maps`, `match_player_stats`, etc.) are not yet migrated to production. However, you can still test the live scoring functionality with existing matches.

## Available Test Matches

### Match #2 (BO1 - Test Alpha vs Test Omega)
- **Status**: Live
- **Format**: Best of 1
- **Teams**: Test Alpha (NA) vs Test Omega (APAC)
- **Live Scoring URL**: https://staging.mrvl.net/admin/matches/2/live-scoring

## Testing Live Scoring Features

### 1. Access Live Scoring Interface
```bash
# View match in admin panel
https://staging.mrvl.net/admin/matches/2
```

### 2. Test Score Updates
In the live scoring interface, you can:
- Update round scores for Domination maps
- Track payload progress for Convoy maps
- Monitor capture progress for Convergence maps

### 3. Test Hero Switching
- Click on any player's hero to change it
- All 39 Marvel Rivals heroes are available
- Heroes are categorized by role (Vanguard, Duelist, Strategist)

### 4. Test Player Stats
Update individual player statistics:
- Eliminations
- Deaths
- Assists
- Damage Dealt
- Healing Done (Strategists)
- Damage Blocked (Vanguards)

### 5. Test Timer Functionality
- Start/Stop match timer
- Preparation phase countdown (45 seconds)
- Map-specific timers based on game mode

### 6. Test Real-time Synchronization
1. Open the match in multiple browser tabs
2. Make updates in one tab
3. See changes reflect immediately in other tabs

## Testing Without Full Database

For now, you can test with limited functionality:

### Get Match List
```bash
curl -X GET "https://staging.mrvl.net/api/matches?limit=10" \
  -H "Accept: application/json" | jq
```

### Get Specific Match
```bash
curl -X GET "https://staging.mrvl.net/api/matches/2" \
  -H "Accept: application/json" | jq
```

### Update Match Status (Admin Only)
```bash
curl -X PATCH "https://staging.mrvl.net/api/admin/matches/2" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "status": "live",
    "team1_score": 1,
    "team2_score": 0
  }'
```

## Features to Test

1. **Score Management**
   - Round-based scoring for Domination
   - Payload progress for Convoy
   - Multi-phase scoring for Convergence

2. **Hero Management**
   - All 39 heroes available
   - Role-based filtering
   - Quick hero swap functionality

3. **Timer System**
   - Match timer with pause/resume
   - Preparation phase (45s)
   - Overtime tracking

4. **Player Performance**
   - Individual stat tracking
   - Team totals
   - Per-map statistics

5. **Format Support**
   - BO1 (Single map)
   - BO3 (First to 2)
   - BO5 (First to 3)
   - BO7 (First to 4)
   - BO9 (First to 5)

## Next Steps

Once the database migrations are complete:
1. Run `php artisan migrate` to create missing tables
2. Use `php artisan test:matches` to create test data
3. Full API endpoints will be available

For now, use the admin panel interface at:
**https://staging.mrvl.net/admin/matches/2/live-scoring**