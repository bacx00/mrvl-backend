<?php

require_once __DIR__ . '/vendor/autoload.php';
use App\Services\MentionService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mentionService = new MentionService();

$testCases = [
    "Check out @team:TSM and @team:C9",
    "Great play by @player:shroud and @player:ninja",
    "Tournament @team:G2 vs @team:FNC with @player:caps and @testuser watching"
];

foreach ($testCases as $content) {
    echo "\nTesting: $content\n";
    $mentions = $mentionService->extractMentions($content);
    echo "Extracted " . count($mentions) . " mentions:\n";
    
    foreach ($mentions as $mention) {
        echo "  - {$mention['mention_text']} (type: {$mention['type']}, id: {$mention['id']}, name: {$mention['name']})\n";
    }
}