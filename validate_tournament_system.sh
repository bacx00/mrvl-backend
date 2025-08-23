#!/bin/bash

# Tournament, Bracket & Rankings System Validation Script
# Tests core functionality of the Events system

echo "========================================"
echo "Tournament & Bracket System Validation"
echo "========================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Base URL
BASE_URL="https://staging.mrvl.net/api"

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to test an endpoint
test_endpoint() {
    local endpoint=$1
    local description=$2
    local method=${3:-GET}
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$endpoint")
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -X $method "$BASE_URL$endpoint")
    fi
    
    if [ "$response" = "200" ] || [ "$response" = "201" ]; then
        echo -e "${GREEN}✓${NC} $description"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗${NC} $description (HTTP $response)"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

# Function to test database
test_database() {
    local table=$1
    local description=$2
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    count=$(php /var/www/mrvl-backend/artisan tinker --execute="echo App\Models\\\\${table}::count();" 2>/dev/null | grep -o '[0-9]*' | head -1)
    
    if [ ! -z "$count" ] && [ "$count" -ge "0" ]; then
        echo -e "${GREEN}✓${NC} $description (Found: $count records)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}✗${NC} $description"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
}

echo "1. Testing Tournament System"
echo "----------------------------"
test_endpoint "/public/tournaments" "Tournament List API"
test_database "Tournament" "Tournament Model & Database"
test_endpoint "/public/events" "Events List API"
test_database "Event" "Event Model & Database"
echo ""

echo "2. Testing Bracket System"
echo "-------------------------"
test_endpoint "/brackets" "Brackets List API"
test_database "Bracket" "Bracket Model & Database"
test_database "BracketMatch" "BracketMatch Model & Database"
test_database "BracketStage" "BracketStage Model & Database"
test_endpoint "/live-matches" "Live Matches API"
echo ""

echo "3. Testing Rankings System"
echo "--------------------------"
test_endpoint "/rankings" "Player Rankings API"
test_endpoint "/rankings/teams" "Team Rankings API"
test_endpoint "/rankings/distribution" "Rank Distribution API"
test_database "Player" "Player Rankings Database"
test_database "Team" "Team Rankings Database"
echo ""

echo "4. Testing Tournament Features"
echo "------------------------------"

# Check for Swiss system support
php /var/www/mrvl-backend/artisan tinker --execute="
    \$tournament = App\Models\Tournament::first();
    if (\$tournament) {
        \$formats = App\Models\Tournament::FORMATS;
        if (isset(\$formats['swiss'])) {
            echo 'SWISS_OK';
        }
    }
" 2>/dev/null | grep -q "SWISS_OK"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Swiss System Support"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗${NC} Swiss System Support"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check for Double Elimination support
php /var/www/mrvl-backend/artisan tinker --execute="
    \$formats = App\Models\Tournament::FORMATS;
    if (isset(\$formats['double_elimination'])) {
        echo 'DOUBLE_ELIM_OK';
    }
" 2>/dev/null | grep -q "DOUBLE_ELIM_OK"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Double Elimination Support"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗${NC} Double Elimination Support"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

# Check for Round Robin support
php /var/www/mrvl-backend/artisan tinker --execute="
    \$formats = App\Models\Tournament::FORMATS;
    if (isset(\$formats['round_robin'])) {
        echo 'ROUND_ROBIN_OK';
    }
" 2>/dev/null | grep -q "ROUND_ROBIN_OK"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Round Robin Support"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗${NC} Round Robin Support"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

echo ""

echo "5. Testing Match Formats"
echo "------------------------"

# Check for Marvel Rivals match formats
php /var/www/mrvl-backend/artisan tinker --execute="
    \$formats = App\Models\Tournament::MATCH_FORMATS;
    \$required = ['bo1', 'bo3', 'bo5', 'bo7'];
    \$missing = [];
    foreach (\$required as \$format) {
        if (!isset(\$formats[\$format])) {
            \$missing[] = \$format;
        }
    }
    if (empty(\$missing)) {
        echo 'ALL_FORMATS_OK';
    }
" 2>/dev/null | grep -q "ALL_FORMATS_OK"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Marvel Rivals Match Formats (Bo1, Bo3, Bo5, Bo7)"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗${NC} Marvel Rivals Match Formats"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

echo ""

echo "6. Testing Tournament Phases"
echo "----------------------------"

# Check tournament phases
php /var/www/mrvl-backend/artisan tinker --execute="
    \$phases = App\Models\Tournament::PHASES;
    \$required = ['registration', 'check_in', 'group_stage', 'swiss_rounds', 'upper_bracket', 'lower_bracket', 'grand_final'];
    \$missing = [];
    foreach (\$required as \$phase) {
        if (!isset(\$phases[\$phase])) {
            \$missing[] = \$phase;
        }
    }
    if (empty(\$missing)) {
        echo 'ALL_PHASES_OK';
    }
" 2>/dev/null | grep -q "ALL_PHASES_OK"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Tournament Phase System"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}✗${NC} Tournament Phase System"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi
TOTAL_TESTS=$((TOTAL_TESTS + 1))

echo ""

# Summary
echo "========================================"
echo "VALIDATION SUMMARY"
echo "========================================"
echo "Total Tests: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
echo -e "${RED}Failed: $FAILED_TESTS${NC}"

SUCCESS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo "Success Rate: $SUCCESS_RATE%"
echo ""

if [ $SUCCESS_RATE -eq 100 ]; then
    echo -e "${GREEN}✅ ALL TESTS PASSED!${NC}"
    echo "Tournament, Bracket & Rankings systems are fully operational."
elif [ $SUCCESS_RATE -ge 80 ]; then
    echo -e "${YELLOW}⚠️ MOSTLY PASSING${NC}"
    echo "Systems are functional but some issues need attention."
else
    echo -e "${RED}❌ CRITICAL ISSUES DETECTED${NC}"
    echo "Multiple system failures require immediate attention."
fi

echo ""
echo "Validation complete at $(date)"

# Save report
REPORT_FILE="/var/www/mrvl-backend/tournament_validation_$(date +%Y%m%d_%H%M%S).txt"
{
    echo "Tournament System Validation Report"
    echo "Date: $(date)"
    echo "Total Tests: $TOTAL_TESTS"
    echo "Passed: $PASSED_TESTS"
    echo "Failed: $FAILED_TESTS"
    echo "Success Rate: $SUCCESS_RATE%"
} > "$REPORT_FILE"

echo "Report saved to: $REPORT_FILE"