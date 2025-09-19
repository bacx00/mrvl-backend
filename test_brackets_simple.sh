#!/bin/bash

echo "🏆 Testing Comprehensive Manual Bracket System"
echo "=============================================="

# Test API endpoints
API_BASE="https://staging.mrvl.net/api"

echo ""
echo "1. Testing Manual Bracket Formats Endpoint..."
echo "GET ${API_BASE}/public/manual-bracket/formats"
response=$(curl -s -X GET "${API_BASE}/public/manual-bracket/formats" -H "Accept: application/json")
echo "Response: $response"

echo ""
echo "2. Testing Tournament Formats..."
echo "GET ${API_BASE}/game-data/tournaments"
response=$(curl -s -X GET "${API_BASE}/game-data/tournaments" -H "Accept: application/json")
echo "Response: $response"

echo ""
echo "3. Testing Frontend Components..."
if [ -f "/var/www/mrvl-frontend/frontend/src/components/admin/ManualBracketEditor.js" ]; then
    echo "✅ ManualBracketEditor.js exists"
    # Check for BO selection
    if grep -q "BO1\|BO3\|BO5\|BO7\|BO9\|BO11" "/var/www/mrvl-frontend/frontend/src/components/admin/ManualBracketEditor.js"; then
        echo "✅ Best-Of selection found in component"
    else
        echo "❌ Best-Of selection NOT found in component"
    fi
else
    echo "❌ ManualBracketEditor.js NOT found"
fi

if [ -f "/var/www/mrvl-frontend/frontend/src/components/admin/ComprehensiveBracketManager.js" ]; then
    echo "✅ ComprehensiveBracketManager.js exists"
else
    echo "❌ ComprehensiveBracketManager.js NOT found"
fi

echo ""
echo "4. Testing Database Tables..."
cd /var/www/mrvl-backend

# Check if migrations were applied
if ./artisan tinker --execute="DB::table('manual_brackets')->count()" 2>/dev/null; then
    echo "✅ manual_brackets table exists"
else
    echo "❌ manual_brackets table NOT found"
fi

echo ""
echo "5. Testing Routes..."
if ./artisan route:list | grep -q "comprehensive-brackets"; then
    echo "✅ Comprehensive bracket routes found"
else
    echo "❌ Comprehensive bracket routes NOT found"
fi

echo ""
echo "6. Testing Controllers..."
if [ -f "app/Http/Controllers/ManualBracketController.php" ]; then
    echo "✅ ManualBracketController exists"
    if grep -q "getFormats\|createManualBracket\|updateMatchScore" "app/Http/Controllers/ManualBracketController.php"; then
        echo "✅ Required methods found"
    else
        echo "❌ Required methods NOT found"
    fi
else
    echo "❌ ManualBracketController NOT found"
fi

echo ""
echo "=============================================="
echo "Test completed!"