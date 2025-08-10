<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MentionService;

$mentionService = new MentionService();
$testContent = 'Hey @Jhonny, check out @team:1T and @player:delenaa for the match!';

echo "Test content: {$testContent}\n";
$mentions = $mentionService->extractMentions($testContent);

if (!empty($mentions)) {
    echo "Mentions found: " . count($mentions) . "\n";
    foreach ($mentions as $mention) {
        echo "- {$mention['type']}: {$mention['mention_text']} -> {$mention['display_name']} (ID: {$mention['id']})\n";
    }
} else {
    echo "No mentions found\n";
}