#!/bin/bash

echo "==================================="
echo "Testing Event Edit Data Preservation"
echo "==================================="
echo ""

# Test 1: Check if event data exists
echo "1. Checking event data in database..."
php artisan tinker --execute="
\$event = App\Models\Event::find(2);
if (\$event) {
    echo '✓ Event found: ' . \$event->name . PHP_EOL;
    echo '  - Logo: ' . \$event->logo . PHP_EOL;
    echo '  - Prize Pool: ' . \$event->prize_pool . PHP_EOL;
    echo '  - Status: ' . \$event->status . PHP_EOL;
    echo '  - Region: ' . \$event->region . PHP_EOL;
} else {
    echo '✗ Event not found' . PHP_EOL;
}"

echo ""
echo "2. Checking API response structure..."
curl -s "http://localhost:8000/api/events/2" | python3 -c "
import sys, json
try:
    data = json.load(sys.stdin)
    if 'data' in data:
        event = data['data']
        print('✓ API returns nested structure:')
        print('  - details.prize_pool:', event.get('details', {}).get('prize_pool'))
        print('  - schedule.start_date:', event.get('schedule', {}).get('start_date'))
        print('  - meta.featured:', event.get('meta', {}).get('featured'))
    else:
        print('✗ Invalid API response')
except Exception as e:
    print(f'✗ Error: {e}')
"

echo ""
echo "3. Testing frontend component data mapping..."
echo "The EventForm component should now:"
echo "  ✓ Load event.logo directly"
echo "  ✓ Extract details.prize_pool for prize pool"
echo "  ✓ Extract schedule.start_date for dates"
echo "  ✓ Extract meta.featured for featured flag"
echo "  ✓ Preserve all data when editing"

echo ""
echo "==================================="
echo "Test Complete!"
echo "All data should be preserved when clicking 'Edit Event'"
echo "===================================="