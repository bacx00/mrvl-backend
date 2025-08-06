<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Testing News Mentions System\n";
echo "===========================\n\n";

// Get a recent news comment with mentions
$comment = DB::table('news_comments')
    ->where('content', 'like', '%@%')
    ->orderBy('created_at', 'desc')
    ->first();

if ($comment) {
    echo "Found comment ID: {$comment->id}\n";
    echo "Content: {$comment->content}\n\n";
    
    // Check mentions in database
    $mentions = DB::table('mentions')
        ->where('mentionable_type', 'news_comment')
        ->where('mentionable_id', $comment->id)
        ->get();
    
    echo "Mentions found in database: " . $mentions->count() . "\n\n";
    
    foreach ($mentions as $mention) {
        echo "- Type: {$mention->mentioned_type}\n";
        echo "  ID: {$mention->mentioned_id}\n";
        echo "  Text: {$mention->mention_text}\n";
        echo "  Position: {$mention->position_start} - {$mention->position_end}\n";
        
        // Get the actual entity
        switch($mention->mentioned_type) {
            case 'player':
                $player = DB::table('players')->where('id', $mention->mentioned_id)->first();
                if ($player) {
                    echo "  Player: {$player->username} ({$player->real_name})\n";
                }
                break;
            case 'team':
                $team = DB::table('teams')->where('id', $mention->mentioned_id)->first();
                if ($team) {
                    echo "  Team: {$team->name}\n";
                }
                break;
            case 'user':
                $user = DB::table('users')->where('id', $mention->mentioned_id)->first();
                if ($user) {
                    echo "  User: {$user->name}\n";
                }
                break;
        }
        echo "\n";
    }
} else {
    echo "No comments with mentions found.\n";
    echo "Creating a test comment with mentions...\n\n";
    
    // Find a player and user to mention
    $player = DB::table('players')->first();
    $user = DB::table('users')->where('role', '!=', 'admin')->first();
    $news = DB::table('news')->first();
    
    if ($player && $user && $news) {
        $content = "Great play by @{$player->username}! Thanks @{$user->name} for sharing.";
        
        $commentId = DB::table('news_comments')->insertGetId([
            'news_id' => $news->id,
            'user_id' => 1, // Admin user
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        echo "Created test comment ID: $commentId\n";
        echo "Content: $content\n\n";
        
        // Test the mention extraction
        echo "Now check if mentions are properly processed when loading the news article.\n";
    }
}

echo "\nDone!\n";