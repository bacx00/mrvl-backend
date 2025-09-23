#!/bin/bash

echo "=========================================="
echo "CHECKING ALL FUCKING SECTIONS"
echo "=========================================="

# Backend Database Check
echo -e "\n[1] BACKEND DATABASE:"
cd /var/www/mrvl-backend
php artisan tinker --execute="
    \$event = DB::table('events')->where('id', 2)->first();
    echo 'Name: ' . \$event->name . PHP_EOL;
    echo 'Status: ' . \$event->status . PHP_EOL;
    echo 'Featured: ' . (\$event->featured ? 'TRUE' : 'FALSE') . PHP_EOL;
"

# API Public Events
echo -e "\n[2] PUBLIC API /api/events:"
curl -s "https://staging.mrvl.net/api/events" | jq '.data[0] | {id, name, status, featured}' 2>/dev/null || echo "API Error"

# API Event Detail
echo -e "\n[3] EVENT DETAIL API /api/events/2:"
curl -s "https://staging.mrvl.net/api/events/2" | jq '.data | {id, name, status, featured}' 2>/dev/null || echo "API Error"

# Homepage Featured Events
echo -e "\n[4] HOMEPAGE FEATURED CHECK:"
curl -s "https://staging.mrvl.net/api/events" | jq '[.data[] | select(.featured == true)] | length' 2>/dev/null || echo "0"
echo " featured events found"

# Admin Events (needs auth)
echo -e "\n[5] ADMIN EVENTS SECTION:"
echo "Checking admin controller response..."
cd /var/www/mrvl-backend
php artisan tinker --execute="
    \$controller = new \App\Http\Controllers\Admin\AdminEventsController();
    \$request = new \Illuminate\Http\Request();
    \$response = \$controller->index(\$request);
    \$data = json_decode(\$response->content(), true);
    if(isset(\$data['data'][0])) {
        echo 'First Event: ' . \$data['data'][0]['name'] . PHP_EOL;
        echo 'Status: ' . \$data['data'][0]['status'] . PHP_EOL;
        echo 'Featured: ' . (\$data['data'][0]['featured'] ? 'TRUE' : 'FALSE') . PHP_EOL;
    } else {
        echo 'No events found';
    }
"

echo -e "\n=========================================="
echo "DONE CHECKING ALL SECTIONS"
echo "=========================================="