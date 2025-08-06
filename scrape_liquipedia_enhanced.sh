#!/bin/bash

echo "Enhanced Liquipedia Scraping Script for Marvel Rivals"
echo "====================================================="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to check if command succeeded
check_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ $1 completed successfully${NC}"
    else
        echo -e "${RED}✗ $1 failed${NC}"
        exit 1
    fi
}

# Change to backend directory
cd /var/www/mrvl-backend

echo -e "\n${YELLOW}Step 1: Running database migrations...${NC}"
php artisan migrate --force
check_status "Database migrations"

echo -e "\n${YELLOW}Step 2: Clearing cache...${NC}"
php artisan cache:clear
php artisan config:clear
check_status "Cache clearing"

echo -e "\n${YELLOW}Step 3: Starting enhanced Liquipedia scraping...${NC}"
echo "This will scrape all 5 Marvel Rivals tournaments with complete data:"
echo "- North America Invitational 2025"
echo "- EMEA Ignite Stage 1"
echo "- Asia Ignite Stage 1"
echo "- Americas Ignite Stage 1"
echo "- Oceania Ignite Stage 1"
echo ""

# Run the enhanced scraper
php artisan liquipedia:scrape-enhanced
check_status "Enhanced scraping"

echo -e "\n${YELLOW}Step 4: Generating data report...${NC}"
php artisan tinker --execute="
    echo '=== MARVEL RIVALS TOURNAMENT DATA REPORT ===' . PHP_EOL;
    echo 'Events: ' . App\Models\Event::count() . PHP_EOL;
    echo 'Teams: ' . App\Models\Team::count() . PHP_EOL;
    echo 'Players: ' . App\Models\Player::count() . PHP_EOL;
    echo 'Matches: ' . App\Models\GameMatch::count() . PHP_EOL;
    echo 'Total Prize Pool: $' . number_format(App\Models\Event::sum('prize_pool')) . PHP_EOL;
    echo PHP_EOL;
    echo '=== REGIONAL BREAKDOWN ===' . PHP_EOL;
    App\Models\Team::select('region', DB::raw('count(*) as count'))
        ->groupBy('region')
        ->get()
        ->each(function(\$item) {
            echo \$item->region . ': ' . \$item->count . ' teams' . PHP_EOL;
        });
    echo PHP_EOL;
    echo '=== SOCIAL MEDIA COVERAGE ===' . PHP_EOL;
    echo 'Teams with social media: ' . App\Models\Team::whereNotNull('twitter')
        ->orWhereNotNull('instagram')
        ->orWhereNotNull('youtube')
        ->count() . PHP_EOL;
    echo 'Players with social media: ' . App\Models\Player::whereNotNull('twitter')
        ->orWhereNotNull('twitch')
        ->count() . PHP_EOL;
"

echo -e "\n${GREEN}✓ Enhanced scraping completed successfully!${NC}"
echo -e "\nYou can now access the complete tournament data through the API endpoints."
echo "All teams, players, matches, and standings have been imported with:"
echo "- Complete social media links"
echo "- ELO ratings"
echo "- Country/region data"
echo "- Prize money distribution"
echo "- Match results and standings"