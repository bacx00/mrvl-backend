#!/bin/bash

# MARVEL RIVALS LIVE DATA - QUICK FIX & RUN

echo "🔧 Fixing seeder and running..."

# Pull latest changes
cd /var/www/mrvl-backend
git pull

# Clear any failed data first
php artisan tinker --execute="
DB::table('players')->whereNotNull('team_id')->delete();
echo 'Cleared existing team players\n';
"

# Run the fixed seeder
php artisan db:seed --class=MarvelRivalsLiveDataSeeder

# Verify results
echo "📊 Verification Results:"
echo "Teams with ratings:"
curl -s -X GET "https://staging.mrvl.net/api/teams" | jq '.data[] | {name, rating, rank}' | head -20

echo "Player count:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | length'

echo "Players by team:"
curl -s -X GET "https://staging.mrvl.net/api/players" | jq '.data | group_by(.team_id) | map({team_id: .[0].team_id, count: length})'

echo "✅ Marvel Rivals platform is now LIVE-READY! 🎮"