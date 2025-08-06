<?php
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = ['tier', 'format', 'type', 'status'];

foreach ($columns as $column) {
    $result = DB::select("SHOW COLUMNS FROM events WHERE Field = '$column'");
    if (!empty($result)) {
        echo "$column column type: " . $result[0]->Type . "\n";
    }
}