# 🔍 BACKEND DATA INSPECTION COMMANDS

## 📊 **CHECK ALL TEAMS DATA:**

```bash
# Connect to your MySQL database and run these queries:

# 1. Get all teams with complete data
SELECT 
    id, name, short_name, logo, region, country, rating, rank, 
    win_rate, points, record, peak, streak, founded, captain, 
    coach, website, earnings, social_media, achievements,
    created_at, updated_at
FROM teams 
ORDER BY rating DESC;

# 2. Count teams by region
SELECT region, COUNT(*) as team_count 
FROM teams 
GROUP BY region;

# 3. Get team ratings distribution
SELECT 
    name, short_name, region, rating, rank,
    CASE 
        WHEN rating >= 2500 THEN 'Eternity'
        WHEN rating >= 2200 THEN 'Celestial' 
        WHEN rating >= 1900 THEN 'Vibranium'
        WHEN rating >= 1600 THEN 'Diamond'
        WHEN rating >= 1300 THEN 'Platinum'
        WHEN rating >= 1000 THEN 'Gold'
        ELSE 'Silver'
    END as division
FROM teams 
ORDER BY rating DESC;
```

## 👥 **CHECK ALL PLAYERS DATA:**

```bash
# 4. Get all players with team info
SELECT 
    p.id, p.name, p.username, p.real_name, p.role, p.team_id,
    p.main_hero, p.alt_heroes, p.region, p.country, p.rating,
    p.age, p.earnings, p.social_media, p.biography, p.avatar,
    t.name as team_name, t.short_name as team_short
FROM players p
LEFT JOIN teams t ON p.team_id = t.id
ORDER BY p.rating DESC;

# 5. Count players by team
SELECT 
    t.name as team_name, t.short_name,
    COUNT(p.id) as player_count
FROM teams t
LEFT JOIN players p ON t.id = p.team_id
GROUP BY t.id, t.name, t.short_name
ORDER BY player_count DESC;

# 6. Count players by role
SELECT role, COUNT(*) as player_count 
FROM players 
GROUP BY role;

# 7. Players without teams (free agents)
SELECT 
    id, name, username, role, rating, region
FROM players 
WHERE team_id IS NULL
ORDER BY rating DESC;
```

## 🏆 **CHECK MATCHES & EVENTS:**

```bash
# 8. Get all matches with team names
SELECT 
    m.id, m.scheduled_at, m.status, m.format,
    m.team1_score, m.team2_score, m.viewers,
    t1.name as team1_name, t1.short_name as team1_short,
    t2.name as team2_name, t2.short_name as team2_short,
    e.name as event_name
FROM matches m
LEFT JOIN teams t1 ON m.team1_id = t1.id
LEFT JOIN teams t2 ON m.team2_id = t2.id  
LEFT JOIN events e ON m.event_id = e.id
ORDER BY m.scheduled_at DESC;

# 9. Get all events
SELECT 
    id, name, type, status, start_date, end_date,
    prize_pool, team_count, location, organizer
FROM events
ORDER BY start_date DESC;
```

## 📱 **QUICK API CHECKS:**

```bash
# 10. Test your API endpoints
curl -X GET "https://staging.mrvl.net/api/teams" | jq '.'
curl -X GET "https://staging.mrvl.net/api/players" | jq '.'
curl -X GET "https://staging.mrvl.net/api/matches" | jq '.'
curl -X GET "https://staging.mrvl.net/api/events" | jq '.'

# 11. Check specific team
curl -X GET "https://staging.mrvl.net/api/teams/1" | jq '.'

# 12. Check team rankings
curl -X GET "https://staging.mrvl.net/api/rankings" | jq '.'
```

## 💡 **PHP ARTISAN COMMANDS (if you have CLI access):**

```bash
# 13. Check database via Laravel
php artisan tinker

# Then in tinker:
DB::table('teams')->count()
DB::table('players')->count()
DB::table('teams')->select('name', 'rating', 'region')->get()
DB::table('players')->whereNull('team_id')->count() // Free agents
```

---

## 📋 **WHAT TO LOOK FOR:**

### **Teams Issues:**
- ❌ All teams have rating = 1000 (need realistic ratings)
- ❌ Missing player counts (0 players each)
- ❌ Missing earnings, achievements, social media
- ❌ Missing captain, coach information
- ❌ Generic/missing logos

### **Players Issues:**  
- ❌ Likely no players assigned to teams
- ❌ Missing hero specializations
- ❌ Missing realistic ratings
- ❌ Missing player avatars

### **Expected Results:**
After running these queries, you'll see exactly what data is missing and needs to be populated for a professional Marvel Rivals esports platform.

**Run these commands and share the results - then I can create the perfect data population strategy!** 🎮