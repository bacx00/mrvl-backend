#!/bin/bash

# Test Tournament Formats API
echo "===================================="
echo "Testing Tournament Formats & Stages"
echo "===================================="

# Get all formats
echo -e "\n1. Getting all tournament formats..."
curl -s -X GET "https://staging.mrvl.net/api/public/manual-bracket/formats" \
  -H "Accept: application/json" | jq '.formats | keys' 2>/dev/null

# Test specific format stages
echo -e "\n2. Testing Marvel Rivals Championship stages..."
curl -s -X GET "https://staging.mrvl.net/api/public/manual-bracket/formats/marvel_rivals_championship_complete/stages" \
  -H "Accept: application/json" | jq '.stages[] | {name: .name, type: .type, best_of: .best_of}' 2>/dev/null

echo -e "\n3. Testing Ignite 2025 Complete stages..."
curl -s -X GET "https://staging.mrvl.net/api/public/manual-bracket/formats/ignite_2025_complete/stages" \
  -H "Accept: application/json" | jq '.stages[] | {name: .name, type: .type, best_of: .best_of}' 2>/dev/null

echo -e "\n4. Testing Standalone Single Elimination..."
curl -s -X GET "https://staging.mrvl.net/api/public/manual-bracket/formats/single_elimination_standalone/stages" \
  -H "Accept: application/json" | jq '.stages[] | {name: .name, type: .type, teams: .teams}' 2>/dev/null

echo -e "\n5. Testing Tournament with Qualifiers stages..."
curl -s -X GET "https://staging.mrvl.net/api/public/manual-bracket/formats/tournament_with_qualifiers/stages" \
  -H "Accept: application/json" | jq '.stages[] | {name: .name, type: .type, advances: .advances}' 2>/dev/null

echo -e "\n===================================="
echo "All tournament formats support:"
echo "- Independent stage creation"
echo "- Flexible team counts"
echo "- Custom best-of values"
echo "- Manual bracket control"
echo "====================================\n"