#!/bin/bash

echo "====================================="
echo "COMPREHENSIVE EVENT SYSTEM TEST"
echo "====================================="

BASE_URL="https://staging.mrvl.net/api"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test function
test_endpoint() {
    local method=$1
    local endpoint=$2
    local description=$3
    local data=$4

    echo -n "Testing: $description... "

    if [ -z "$data" ]; then
        response=$(curl -s -X $method "$BASE_URL$endpoint" -H "Accept: application/json" -w "\n%{http_code}")
    else
        response=$(curl -s -X $method "$BASE_URL$endpoint" \
            -H "Accept: application/json" \
            -H "Content-Type: application/json" \
            -d "$data" -w "\n%{http_code}")
    fi

    http_code=$(echo "$response" | tail -n 1)
    body=$(echo "$response" | head -n -1)

    if [ "$http_code" -ge "200" ] && [ "$http_code" -lt "300" ]; then
        echo -e "${GREEN}✓${NC} (HTTP $http_code)"
        return 0
    else
        echo -e "${RED}✗${NC} (HTTP $http_code)"
        echo "  Response: $(echo $body | head -c 200)..."
        return 1
    fi
}

echo ""
echo "1. PUBLIC ENDPOINTS"
echo "-------------------"
test_endpoint "GET" "/events" "List all events"
test_endpoint "GET" "/events/2" "Get event details"
test_endpoint "GET" "/events/2/bracket" "Get event bracket"
test_endpoint "GET" "/teams" "List all teams"
test_endpoint "GET" "/matches" "List all matches"

echo ""
echo "2. BRACKET ENDPOINTS"
echo "--------------------"
test_endpoint "GET" "/brackets/event/2" "Get bracket by event ID"
test_endpoint "GET" "/manual-bracket/formats" "Get bracket formats"

echo ""
echo "3. EVENT TEAMS"
echo "--------------"
test_endpoint "GET" "/events/2/teams" "Get event teams"
test_endpoint "GET" "/events/2/matches" "Get event matches"

echo ""
echo "4. CHECKING DATABASE CONSISTENCY"
echo "---------------------------------"
php artisan tinker --execute="
\$event = \App\Models\Event::find(2);
echo 'Event found: ' . (\$event ? 'YES' : 'NO') . PHP_EOL;
if (\$event) {
    echo 'Event name: ' . \$event->name . PHP_EOL;
    echo 'Teams count: ' . \$event->teams()->count() . PHP_EOL;
    echo 'Matches count: ' . \$event->matches()->count() . PHP_EOL;
    echo 'Bracket stages: ' . \$event->bracketStages()->count() . PHP_EOL;
    echo 'Featured: ' . (\$event->featured ? 'YES' : 'NO') . PHP_EOL;
    echo 'Status: ' . \$event->status . PHP_EOL;
}
"

echo ""
echo "5. PERMISSION CHECK"
echo "-------------------"
echo -n "Storage writable: "
if [ -w "/var/www/mrvl-backend/storage" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo -n "Logs writable: "
if [ -w "/var/www/mrvl-backend/storage/logs" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo -n "Laravel log writable: "
if [ -w "/var/www/mrvl-backend/storage/logs/laravel.log" ]; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗${NC}"
fi

echo ""
echo "====================================="
echo "TEST COMPLETED"
echo "====================================="