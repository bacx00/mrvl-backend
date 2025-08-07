<?php

// Change to the Laravel directory
chdir('/var/www/mrvl-backend');

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('teams', 'tag')) {
    Schema::table('teams', function (Blueprint $table) {
        $table->string('tag')->nullable()->after('name');
    });
    echo "✓ Added tag column to teams table\n";
} else {
    echo "- Tag column already exists\n";
}