<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Update article 8 with simpler, working video content
$simpleContent = "Marvel Rivals Championship 2025 is heating up! Check out these amazing highlights:

## Match Highlights

Watch this incredible gameplay showcase:

[youtube:K4DyBUG242c]

## Tournament Overview

Here's the official tournament trailer:

https://www.youtube.com/watch?v=dQw4w9WgXcQ

## Community Highlights

Check out this amazing community montage:

[youtube:jNQXAC9IVRw]

The tournament featured incredible plays from top teams across all regions. The level of competition has been outstanding, with teams pushing the boundaries of what's possible in Marvel Rivals.

## What's Next?

Stay tuned for more coverage as we head into the playoffs next week. The competition is only getting more intense!";

try {
    DB::table('news')
        ->where('id', 8)
        ->update([
            'content' => $simpleContent,
            'updated_at' => now()
        ]);
    
    echo "✅ Article 8 updated with simple video content\n";
    echo "📹 Videos included:\n";
    echo "  - YouTube embed with valid ID (K4DyBUG242c)\n";
    echo "  - YouTube URL (rickroll for testing)\n";
    echo "  - Another YouTube embed (jNQXAC9IVRw)\n";
    echo "\n";
    echo "View at: https://staging.mrvl.net/#news-detail?id=8\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}