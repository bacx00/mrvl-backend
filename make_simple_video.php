<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Ultra simple content with just one working video
$simpleContent = "Marvel Rivals Championship highlights:

[youtube:dQw4w9WgXcQ]

That's it.";

DB::table('news')->where('id', 8)->update([
    'content' => $simpleContent,
    'updated_at' => now()
]);

echo "✅ Made it simple\n";