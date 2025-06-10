#!/bin/bash

echo "🔍 Checking database role column size..."

# Check the role column definition
cd /var/www/mrvl-backend
php artisan tinker --execute="
\$desc = DB::select('DESCRIBE players');
foreach(\$desc as \$col) {
    if(\$col->Field === 'role') {
        echo 'Role column: ' . \$col->Type . PHP_EOL;
        break;
    }
}
"

echo "📊 Current player roles in database:"
php artisan tinker --execute="
\$roles = DB::table('players')->distinct()->pluck('role');
foreach(\$roles as \$role) {
    echo \$role . ' (' . strlen(\$role) . ' chars)' . PHP_EOL;
}
"

echo "🚀 Now running the simplified seeder with Tank/Duelist/Support only..."