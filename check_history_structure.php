<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Get column info
$columns = DB::select("SHOW COLUMNS FROM player_team_history WHERE Field = 'change_type'");
if ($columns) {
    echo "Change Type column info:\n";
    print_r($columns[0]);
}