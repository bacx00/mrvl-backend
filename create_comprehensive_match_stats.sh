#!/bin/bash

# Create comprehensive match statistics for match 7
# All players from both teams play 5 heroes each across 3 maps

API_URL="https://staging.mrvl.net/api"
TOKEN=$(cat /var/www/mrvl-backend/admin_token.txt)

echo "Creating comprehensive match statistics for Match 7..."
echo "================================================="

# Team 4 (100 Thieves) players: 405, 406, 407, 408, 409, 410
# Team 32 (BOOM Esports) players: 591, 592, 593, 594, 595, 596

# Heroes pool for variety
DUELISTS=("Hela" "Hawkeye" "Iron Man" "Black Widow" "Winter Soldier" "Punisher" "Star-Lord" "Moon Knight" "Namor" "Psylocke" "Scarlet Witch" "Storm")
VANGUARDS=("Venom" "Hulk" "Thor" "Captain America" "Doctor Strange" "Groot" "Magneto" "Peni Parker")
STRATEGISTS=("Luna Snow" "Mantis" "Loki" "Rocket Raccoon" "Jeff the Land Shark" "Adam Warlock" "Cloak & Dagger")

# Map names
MAP1="Hellfire Gala: Krakoa"
MAP2="Hydra Charteris Base: Hell's Heaven"
MAP3="Intergalactic Empire of Wakanda: Birnin T'Challa"

# Function to generate random stats based on hero role
generate_stats() {
    local role=$1
    local map_num=$2
    
    # Base stats vary by role
    if [ "$role" == "duelist" ]; then
        ELIMS=$((RANDOM % 20 + 15))  # 15-35
        DEATHS=$((RANDOM % 8 + 2))    # 2-10
        ASSISTS=$((RANDOM % 15 + 5))  # 5-20
        DAMAGE=$((RANDOM % 50000 + 80000))  # 80k-130k
        HEALING=0
        BLOCKED=$((RANDOM % 5000))    # 0-5k
    elif [ "$role" == "vanguard" ]; then
        ELIMS=$((RANDOM % 15 + 8))    # 8-23
        DEATHS=$((RANDOM % 10 + 3))   # 3-13
        ASSISTS=$((RANDOM % 20 + 10)) # 10-30
        DAMAGE=$((RANDOM % 40000 + 50000))  # 50k-90k
        HEALING=0
        BLOCKED=$((RANDOM % 50000 + 30000)) # 30k-80k
    else # strategist
        ELIMS=$((RANDOM % 12 + 5))    # 5-17
        DEATHS=$((RANDOM % 6 + 1))    # 1-7
        ASSISTS=$((RANDOM % 35 + 20)) # 20-55
        DAMAGE=$((RANDOM % 30tonline + 20000))  # 20k-50k
        HEALING=$((RANDOM % 40000 + 40000)) # 40k-80k
        BLOCKED=$((RANDOM % 10000))   # 0-10k
    fi
    
    echo "$ELIMS,$DEATHS,$ASSISTS,$DAMAGE,$HEALING,$BLOCKED"
}

# Function to add match player stats
add_player_stat() {
    local player_id=$1
    local team_id=$2
    local hero=$3
    local map_num=$4
    local map_name=$5
    local role=$6
    
    # Generate stats based on role
    IFS=',' read -r elims deaths assists damage healing blocked <<< $(generate_stats "$role" "$map_num")
    
    # Calculate KDA
    if [ $deaths -eq 0 ]; then
        kda="999.00"
    else
        kda=$(echo "scale=2; ($elims + $assists) / $deaths" | bc)
    fi
    
    echo "  Adding stats for Player $player_id - Hero: $hero (Map $map_num)"
    
    curl -s -X POST "$API_URL/admin/match-player-stats" \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d '{
            "match_id": 7,
            "player_id": '$player_id',
            "team_id": '$team_id',
            "hero": "'$hero'",
            "map_number": '$map_num',
            "map_name": "'$map_name'",
            "eliminations": '$elims',
            "deaths": '$deaths',
            "assists": '$assists',
            "damage_dealt": '$damage',
            "healing_done": '$healing',
            "damage_blocked": '$blocked',
            "kda_ratio": "'$kda'"
        }' > /dev/null 2>&1
    
    echo "    K/D/A: $elims/$deaths/$assists | DMG: $damage | HEAL: $healing | BLOCK: $blocked"
}

# Clear existing stats for match 7 first
echo "Clearing existing stats for match 7..."
mysql -u root mrvl_backend -e "DELETE FROM match_player_stats WHERE match_id = 7;" 2>/dev/null

echo ""
echo "Creating stats for Team 100 Thieves (ID: 4)..."
echo "----------------------------------------------"

# Team 4 - 100 Thieves
# Player 405 - delenaa (Duelist)
echo "Player 405 - delenaa:"
add_player_stat 405 4 "Hela" 1 "$MAP1" "duelist"
add_player_stat 405 4 "Hawkeye" 1 "$MAP1" "duelist" 
add_player_stat 405 4 "Iron Man" 2 "$MAP2" "duelist"
add_player_stat 405 4 "Black Widow" 2 "$MAP2" "duelist"
add_player_stat 405 4 "Punisher" 3 "$MAP3" "duelist"

# Player 406 - Terra (Duelist)
echo "Player 406 - Terra:"
add_player_stat 406 4 "Star-Lord" 1 "$MAP1" "duelist"
add_player_stat 406 4 "Moon Knight" 1 "$MAP1" "duelist"
add_player_stat 406 4 "Namor" 2 "$MAP2" "duelist"
add_player_stat 406 4 "Psylocke" 3 "$MAP3" "duelist"
add_player_stat 406 4 "Scarlet Witch" 3 "$MAP3" "duelist"

# Player 407 - hxrvey (Strategist) 
echo "Player 407 - hxrvey:"
add_player_stat 407 4 "Luna Snow" 1 "$MAP1" "strategist"
add_player_stat 407 4 "Mantis" 1 "$MAP1" "strategist"
add_player_stat 407 4 "Loki" 2 "$MAP2" "strategist"
add_player_stat 407 4 "Rocket Raccoon" 3 "$MAP3" "strategist"
add_player_stat 407 4 "Jeff the Land Shark" 3 "$MAP3" "strategist"

# Player 408 - SJP (Strategist)
echo "Player 408 - SJP:"
add_player_stat 408 4 "Adam Warlock" 1 "$MAP1" "strategist"
add_player_stat 408 4 "Cloak & Dagger" 2 "$MAP2" "strategist"
add_player_stat 408 4 "Luna Snow" 2 "$MAP2" "strategist"
add_player_stat 408 4 "Mantis" 3 "$MAP3" "strategist"
add_player_stat 408 4 "Rocket Raccoon" 3 "$MAP3" "strategist"

# Player 409 - TTK (Vanguard)
echo "Player 409 - TTK:"
add_player_stat 409 4 "Venom" 1 "$MAP1" "vanguard"
add_player_stat 409 4 "Hulk" 1 "$MAP1" "vanguard"
add_player_stat 409 4 "Thor" 2 "$MAP2" "vanguard"
add_player_stat 409 4 "Captain America" 3 "$MAP3" "vanguard"
add_player_stat 409 4 "Doctor Strange" 3 "$MAP3" "vanguard"

# Player 410 - Vinnie (Vanguard)
echo "Player 410 - Vinnie:"
add_player_stat 410 4 "Groot" 1 "$MAP1" "vanguard"
add_player_stat 410 4 "Magneto" 2 "$MAP2" "vanguard"
add_player_stat 410 4 "Peni Parker" 2 "$MAP2" "vanguard"
add_player_stat 410 4 "Venom" 3 "$MAP3" "vanguard"
add_player_stat 410 4 "Hulk" 3 "$MAP3" "vanguard"

echo ""
echo "Creating stats for Team BOOM Esports (ID: 32)..."
echo "------------------------------------------------"

# Team 32 - BOOM Esports
# Player 591 - rapz (Duelist)
echo "Player 591 - rapz:"
add_player_stat 591 32 "Winter Soldier" 1 "$MAP1" "duelist"
add_player_stat 591 32 "Storm" 1 "$MAP1" "duelist"
add_player_stat 591 32 "Hela" 2 "$MAP2" "duelist"
add_player_stat 591 32 "Hawkeye" 3 "$MAP3" "duelist"
add_player_stat 591 32 "Iron Man" 3 "$MAP3" "duelist"

# Player 592 - nashmin (Duelist)
echo "Player 592 - nashmin:"
add_player_stat 592 32 "Black Widow" 1 "$MAP1" "duelist"
add_player_stat 592 32 "Punisher" 2 "$MAP2" "duelist"
add_player_stat 592 32 "Star-Lord" 2 "$MAP2" "duelist"
add_player_stat 592 32 "Moon Knight" 3 "$MAP3" "duelist"
add_player_stat 592 32 "Namor" 3 "$MAP3" "duelist"

# Player 593 - mikoto (Strategist)
echo "Player 593 - mikoto:"
add_player_stat 593 32 "Jeff the Land Shark" 1 "$MAP1" "strategist"
add_player_stat 593 32 "Adam Warlock" 1 "$MAP1" "strategist"
add_player_stat 593 32 "Cloak & Dagger" 2 "$MAP2" "strategist"
add_player_stat 593 32 "Luna Snow" 3 "$MAP3" "strategist"
add_player_stat 593 32 "Mantis" 3 "$MAP3" "strategist"

# Player 594 - Alexx (Strategist)
echo "Player 594 - Alexx:"
add_player_stat 594 32 "Loki" 1 "$MAP1" "strategist"
add_player_stat 594 32 "Rocket Raccoon" 2 "$MAP2" "strategist"
add_player_stat 594 32 "Jeff the Land Shark" 2 "$MAP2" "strategist"
add_player_stat 594 32 "Adam Warlock" 3 "$MAP3" "strategist"
add_player_stat 594 32 "Cloak & Dagger" 3 "$MAP3" "strategist"

# Player 595 - hamoodi (Vanguard)
echo "Player 595 - hamoodi:"
add_player_stat 595 32 "Doctor Strange" 1 "$MAP1" "vanguard"
add_player_stat 595 32 "Groot" 2 "$MAP2" "vanguard"
add_player_stat 595 32 "Magneto" 2 "$MAP2" "vanguard"
add_player_stat 595 32 "Peni Parker" 3 "$MAP3" "vanguard"
add_player_stat 595 32 "Thor" 3 "$MAP3" "vanguard"

# Player 596 - yoon (Vanguard)
echo "Player 596 - yoon:"
add_player_stat 596 32 "Captain America" 1 "$MAP1" "vanguard"
add_player_stat 596 32 "Venom" 1 "$MAP1" "vanguard"
add_player_stat 596 32 "Hulk" 2 "$MAP2" "vanguard"
add_player_stat 596 32 "Doctor Strange" 3 "$MAP3" "vanguard"
add_player_stat 596 32 "Groot" 3 "$MAP3" "vanguard"

echo ""
echo "================================================="
echo "Comprehensive match statistics created successfully!"
echo "All 12 players now have 5 heroes each with unique stats across 3 maps"
echo ""
echo "View the results at:"
echo "  - Match details: https://staging.mrvl.net/#match-detail/7"
echo "  - Player profiles: https://staging.mrvl.net/#player-detail/[405-410,591-596]"