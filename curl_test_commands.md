# Marvel Rivals Match System - CURL Test Commands

## 🎮 Test Match Creation

### 1. Create Test Matches (All Formats: BO1, BO3, BO5, BO7, BO9)
```bash
curl -X POST https://staging.mrvl.net/api/test/matches \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"clean": true}'
```

### 2. Create Test Matches (Keep Existing)
```bash
curl -X POST https://staging.mrvl.net/api/test/matches \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"clean": false}'
```

## 📊 Get Match Data

### 3. Get Live Match Data (replace {id} with match ID)
```bash
curl -X GET https://staging.mrvl.net/api/test/matches/{id}/data \
  -H "Accept: application/json"
```

### 4. Get All Matches
```bash
curl -X GET https://staging.mrvl.net/api/matches \
  -H "Accept: application/json"
```

### 5. Get Live Matches Only
```bash
curl -X GET https://staging.mrvl.net/api/matches/live \
  -H "Accept: application/json"
```

## 🔴 Simulate Live Updates

### 6. Simulate Score Update
```bash
curl -X POST https://staging.mrvl.net/api/test/matches/{id}/simulate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "score"}'
```

### 7. Simulate Hero Swap
```bash
curl -X POST https://staging.mrvl.net/api/test/matches/{id}/simulate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "hero_swap"}'
```

### 8. Simulate Player Stats Update
```bash
curl -X POST https://staging.mrvl.net/api/test/matches/{id}/simulate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "player_stats"}'
```

### 9. Complete Current Map & Start Next
```bash
curl -X POST https://staging.mrvl.net/api/test/matches/{id}/simulate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "map_complete"}'
```

## 🏆 Live Scoring Endpoints

### 10. Get Live Scoreboard Data
```bash
curl -X GET https://staging.mrvl.net/api/matches/{id}/live-scoreboard \
  -H "Accept: application/json"
```

### 11. Update Match Score (requires auth)
```bash
curl -X POST https://staging.mrvl.net/api/admin/matches/{id}/update-score \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "team1_score": 2,
    "team2_score": 1,
    "map_scores": {
      "0": {"team1": 3, "team2": 1},
      "1": {"team1": 0, "team2": 3},
      "2": {"team1": 3, "team2": 2}
    }
  }'
```

### 12. Update Player Stats (requires auth)
```bash
curl -X POST https://staging.mrvl.net/api/admin/matches/{matchId}/maps/{mapId}/player-stats \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "player_id": 1,
    "hero": "Spider-Man",
    "eliminations": 15,
    "deaths": 5,
    "assists": 10,
    "damage_dealt": 12000,
    "healing_done": 0
  }'
```

## 🧪 Full Test Flow Example

```bash
# 1. Create test matches
RESPONSE=$(curl -s -X POST https://staging.mrvl.net/api/test/matches \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"clean": true}')

# 2. Extract first live match ID (requires jq)
MATCH_ID=$(echo $RESPONSE | jq -r '.live_matches[0]')

# 3. Get match data
curl -X GET https://staging.mrvl.net/api/test/matches/$MATCH_ID/data \
  -H "Accept: application/json" | jq

# 4. Simulate some updates
curl -X POST https://staging.mrvl.net/api/test/matches/$MATCH_ID/simulate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"type": "score"}' | jq

# 5. Check live scoreboard
curl -X GET https://staging.mrvl.net/api/matches/$MATCH_ID/live-scoreboard \
  -H "Accept: application/json" | jq
```

## 📝 Notes

- Replace `{id}` with actual match ID from the create response
- Replace `{token}` with your authentication token for admin endpoints
- Add `| jq` at the end of commands for pretty JSON output (requires jq installed)
- The test endpoints only work in non-production environments
- Live matches will have real-time updates via Pusher websockets

## 🚀 Testing Different Formats

The test match creation will create:
- 1 BO1 match (1 map) - Status: live
- 1 BO3 match (3 maps) - Status: live
- 1 BO5 match (5 maps) - Status: upcoming
- 1 BO7 match (7 maps) - Status: upcoming
- 1 BO9 match (9 maps) - Status: upcoming

Live matches will have:
- First map as "live"
- Previous maps as "completed" (if applicable)
- Future maps as "upcoming"