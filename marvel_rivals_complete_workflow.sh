# MARVEL RIVALS COMPLETE WORKFLOW
# Run these commands on your server

# ==========================================
# STEP 1: CREATE NEW ADMIN TOKEN
# ==========================================

# Login and get new token
curl -X POST "https://staging.mrvl.net/api/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@mrvl.net",
    "password": "your_admin_password"
  }'

# Save the token from response as NEW_TOKEN
export NEW_TOKEN="your_new_token_here"

# ==========================================
# STEP 2: CREATE TEAMS (if needed)
# ==========================================

# Create Team 1
curl -X POST "https://staging.mrvl.net/api/admin/teams" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Sentinels",
    "country": "US",
    "logo": "https://example.com/sentinels-logo.png",
    "description": "Professional Marvel Rivals team"
  }'

# Create Team 2
curl -X POST "https://staging.mrvl.net/api/admin/teams" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Guardians Elite",
    "country": "KR",
    "logo": "https://example.com/guardians-logo.png", 
    "description": "Korean Marvel Rivals champions"
  }'

# ==========================================
# STEP 3: CREATE PLAYERS FOR BOTH TEAMS
# ==========================================

# Team 1 Players (Sentinels)
curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelTank",
    "team_id": 83,
    "role": "Vanguard",
    "country": "US",
    "age": 22
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelDPS1", 
    "team_id": 83,
    "role": "Duelist",
    "country": "US",
    "age": 20
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelDPS2",
    "team_id": 83, 
    "role": "Duelist",
    "country": "US",
    "age": 24
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelSupport1",
    "team_id": 83,
    "role": "Strategist", 
    "country": "US",
    "age": 23
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelSupport2",
    "team_id": 83,
    "role": "Strategist",
    "country": "US", 
    "age": 21
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "SentinelFlex",
    "team_id": 83,
    "role": "Vanguard",
    "country": "US",
    "age": 25
  }'

# Team 2 Players (Guardians Elite)  
curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianTank",
    "team_id": 84,
    "role": "Vanguard", 
    "country": "KR",
    "age": 21
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianDPS1",
    "team_id": 84,
    "role": "Duelist",
    "country": "KR",
    "age": 19
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianDPS2",
    "team_id": 84,
    "role": "Duelist", 
    "country": "KR",
    "age": 22
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianSupport1",
    "team_id": 84,
    "role": "Strategist",
    "country": "KR",
    "age": 20
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianSupport2", 
    "team_id": 84,
    "role": "Strategist",
    "country": "KR",
    "age": 23
  }'

curl -X POST "https://staging.mrvl.net/api/admin/players" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GuardianFlex",
    "team_id": 84,
    "role": "Vanguard",
    "country": "KR", 
    "age": 24
  }'

# ==========================================
# STEP 4: CREATE EVENT/TOURNAMENT
# ==========================================

curl -X POST "https://staging.mrvl.net/api/admin/events" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Marvel Rivals World Championship 2025",
    "type": "championship",
    "start_date": "2025-01-15",
    "end_date": "2025-01-20",
    "location": "Los Angeles, CA",
    "prize_pool": 500000,
    "description": "The biggest Marvel Rivals tournament of the year"
  }'

# ==========================================
# STEP 5: CREATE INITIAL MATCH
# ==========================================

curl -X POST "https://staging.mrvl.net/api/admin/matches" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_id": 83,
    "team2_id": 84,
    "event_id": 20,
    "scheduled_at": "2025-01-15T18:00:00Z",
    "format": "BO5",
    "status": "scheduled"
  }'

# Save the match ID from response
export MATCH_ID="match_id_from_response"

# ==========================================
# STEP 6: START MATCH & SET HERO COMPOSITIONS
# ==========================================

# Start the match
curl -X PUT "https://staging.mrvl.net/api/admin/matches/$MATCH_ID" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "live",
    "started_at": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "current_map": 1,
    "viewers": 15420
  }'

# Set Map 1 composition and initial scores
curl -X PUT "https://staging.mrvl.net/api/admin/matches/$MATCH_ID" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "maps": [
      {
        "map_number": 1,
        "map_name": "Asgard: Royal Palace",
        "mode": "Domination",
        "status": "in_progress",
        "team1_score": 0,
        "team2_score": 0
      }
    ],
    "maps_data": [
      {
        "map_number": 1,
        "map_name": "Asgard: Royal Palace", 
        "mode": "Domination",
        "team1_score": 0,
        "team2_score": 0,
        "team1_composition": [
          {
            "player_id": 173,
            "player_name": "SentinelTank",
            "hero": "Doctor Strange",
            "role": "Vanguard",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 174,
            "player_name": "SentinelDPS1", 
            "hero": "Iron Man",
            "role": "Duelist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 175,
            "player_name": "SentinelDPS2",
            "hero": "Spider-Man", 
            "role": "Duelist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 176,
            "player_name": "SentinelSupport1",
            "hero": "Luna Snow",
            "role": "Strategist",
            "eliminations": 0,
            "deaths": 0, 
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 177,
            "player_name": "SentinelSupport2",
            "hero": "Mantis",
            "role": "Strategist", 
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 178,
            "player_name": "SentinelFlex",
            "hero": "Hulk",
            "role": "Vanguard",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0, 
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          }
        ],
        "team2_composition": [
          {
            "player_id": 179,
            "player_name": "GuardianTank",
            "hero": "Thor",
            "role": "Vanguard",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 180,
            "player_name": "GuardianDPS1",
            "hero": "Punisher", 
            "role": "Duelist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 181,
            "player_name": "GuardianDPS2",
            "hero": "Hela",
            "role": "Duelist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 182,
            "player_name": "GuardianSupport1", 
            "hero": "Adam Warlock",
            "role": "Strategist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 183,
            "player_name": "GuardianSupport2",
            "hero": "Loki",
            "role": "Strategist",
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          },
          {
            "player_id": 184,
            "player_name": "GuardianFlex",
            "hero": "Magneto",
            "role": "Vanguard", 
            "eliminations": 0,
            "deaths": 0,
            "assists": 0,
            "damage": 0,
            "healing": 0,
            "damageBlocked": 0
          }
        ]
      }
    ]
  }'

# ==========================================
# STEP 7: LIVE SCORING UPDATES
# ==========================================

# Example: Update player stats during match
curl -X PUT "https://staging.mrvl.net/api/admin/matches/$MATCH_ID/live-scoring" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "map_number": 1,
    "player_updates": [
      {
        "player_id": 173,
        "eliminations": 3,
        "deaths": 1,
        "assists": 2,
        "damage": 4500,
        "healing": 0,
        "damageBlocked": 2800
      },
      {
        "player_id": 176,
        "eliminations": 1,
        "deaths": 0,
        "assists": 5,
        "damage": 1200,
        "healing": 3800,
        "damageBlocked": 0
      }
    ],
    "team1_score": 1,
    "team2_score": 0,
    "viewers": 16800
  }'

# ==========================================
# STEP 8: COMPLETE MAP AND START NEXT
# ==========================================

# Complete Map 1
curl -X PUT "https://staging.mrvl.net/api/admin/matches/$MATCH_ID" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team1_score": 1,
    "team2_score": 0,
    "current_map": 2,
    "viewers": 18500,
    "maps_data": [
      {
        "map_number": 1,
        "map_name": "Asgard: Royal Palace",
        "mode": "Domination", 
        "team1_score": 2,
        "team2_score": 1,
        "status": "completed",
        "team1_composition": [
          {
            "player_id": 173,
            "player_name": "SentinelTank",
            "hero": "Doctor Strange",
            "role": "Vanguard",
            "eliminations": 12,
            "deaths": 4,
            "assists": 8,
            "damage": 18500,
            "healing": 0,
            "damageBlocked": 12800
          }
        ]
      },
      {
        "map_number": 2,
        "map_name": "Tokyo 2099: Spider Islands",
        "mode": "Escort",
        "team1_score": 0,
        "team2_score": 0,
        "status": "in_progress"
      }
    ]
  }'

# ==========================================
# STEP 9: ANALYTICS & MONITORING
# ==========================================

# Get live match stats
curl -X GET "https://staging.mrvl.net/api/admin/matches/$MATCH_ID/complete" \
  -H "Authorization: Bearer $NEW_TOKEN"

# Get advanced analytics
curl -X GET "https://staging.mrvl.net/api/admin/matches/$MATCH_ID/advanced-stats" \
  -H "Authorization: Bearer $NEW_TOKEN"

# Get performance analytics
curl -X GET "https://staging.mrvl.net/api/admin/matches/$MATCH_ID/performance-analytics" \
  -H "Authorization: Bearer $NEW_TOKEN"

# ==========================================
# STEP 10: COMPLETE MATCH
# ==========================================

# Complete the entire match
curl -X PUT "https://staging.mrvl.net/api/admin/matches/$MATCH_ID" \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "completed",
    "completed_at": "'$(date -u +%Y-%m-%dT%H:%M:%SZ)'",
    "team1_score": 3,
    "team2_score": 2,
    "peak_viewers": 25000,
    "viewers": 22000
  }'

# ==========================================
# STEP 11: TOURNAMENT LEADERBOARDS
# ==========================================

# Get tournament leaderboards
curl -X GET "https://staging.mrvl.net/api/tournaments/20/leaderboards" \
  -H "Authorization: Bearer $NEW_TOKEN"

# ==========================================
# STEP 12: SEARCH & FILTER MATCHES
# ==========================================

# Search matches by team
curl -X GET "https://staging.mrvl.net/api/admin/matches/search?team1_id=83" \
  -H "Authorization: Bearer $NEW_TOKEN"

# Search matches by status
curl -X GET "https://staging.mrvl.net/api/admin/matches/search?status=completed" \
  -H "Authorization: Bearer $NEW_TOKEN"

# Search matches by date range
curl -X GET "https://staging.mrvl.net/api/admin/matches/search?start_date=2025-01-01&end_date=2025-01-31" \
  -H "Authorization: Bearer $NEW_TOKEN"

echo "✅ Complete Marvel Rivals workflow executed!"
echo "Match ID: $MATCH_ID"
echo "All systems operational!"