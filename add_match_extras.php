<?php
require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\DB;
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎮 Adding comments and URLs to Match 13...\n\n";

$matchId = 13;

// Update match with URLs
DB::table('matches')->where('id', $matchId)->update([
    'stream_url' => 'https://www.twitch.tv/marvel_rivals',
    'vod_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'betting_url' => 'https://www.bet365.com/esports/marvel-rivals/sentinels-vs-nrg',
    'updated_at' => now()
]);

echo "✅ Added URLs to match:\n";
echo "  - Stream: https://www.twitch.tv/marvel_rivals\n";
echo "  - VOD: https://www.youtube.com/watch?v=dQw4w9WgXcQ\n";
echo "  - Betting: https://www.bet365.com/esports/marvel-rivals/sentinels-vs-nrg\n\n";

// Get some users to create comments
$users = DB::table('users')->whereIn('id', [1, 2, 3, 81, 82, 83])->get();
if ($users->isEmpty()) {
    // Fallback to any users
    $users = DB::table('users')->limit(6)->get();
}

echo "Creating comments...\n";

// Parent comments
$comments = [
    [
        'user' => $users[0] ?? (object)['id' => 1],
        'content' => "SENTINELS LOOKING STRONG! 🔥 That Map 1 performance was insane, especially @player:Coluge with the Spider-Man plays!",
        'created_at' => now()->subMinutes(45)
    ],
    [
        'user' => $users[1] ?? (object)['id' => 2],
        'content' => "NRG bounced back hard on Map 2 though. @player:Titan absolutely dominated with 134 eliminations across 5 heroes! This BO5 is going to be legendary 🎮",
        'created_at' => now()->subMinutes(40)
    ],
    [
        'user' => $users[2] ?? (object)['id' => 3],
        'content' => "Anyone else notice how many hero switches we're seeing? This meta is so diverse! Every player switching between 5 different heroes per map is wild",
        'created_at' => now()->subMinutes(35)
    ],
    [
        'user' => $users[3] ?? (object)['id' => 81],
        'content' => "Map 3 is LIVE right now and it's neck and neck! @team:Sentinels needs this map to secure match point. The pressure is real! 💪",
        'created_at' => now()->subMinutes(20)
    ],
    [
        'user' => $users[4] ?? (object)['id' => 82],
        'content' => "The hero diversity in this match is incredible. Seeing players flex between DPS, Tank, and Support roles shows true skill. This is peak Marvel Rivals gameplay!",
        'created_at' => now()->subMinutes(15)
    ],
    [
        'user' => $users[5] ?? (object)['id' => 83],
        'content' => "Predictions for Map 4 and 5? I think @team:NRG takes it 3-2 in the reverse sweep. They have momentum after that Map 2 domination!",
        'created_at' => now()->subMinutes(10)
    ]
];

$commentIds = [];
foreach ($comments as $index => $comment) {
    $commentId = DB::table('match_comments')->insertGetId([
        'match_id' => $matchId,
        'user_id' => $comment['user']->id,
        'content' => $comment['content'],
        'parent_id' => null,
        'upvotes' => rand(5, 50),
        'downvotes' => rand(0, 5),
        'is_edited' => false,
        'created_at' => $comment['created_at'],
        'updated_at' => $comment['created_at']
    ]);
    $commentIds[] = $commentId;
    echo "  ✓ Added comment from user {$comment['user']->id}\n";
}

// Add some replies
$replies = [
    [
        'parent' => $commentIds[0],
        'user' => $users[2] ?? (object)['id' => 3],
        'content' => "Facts! @player:Coluge's movement was insane. That 132 elimination game across 5 heroes is MVP worthy 🏆",
        'created_at' => now()->subMinutes(42)
    ],
    [
        'parent' => $commentIds[0],
        'user' => $users[4] ?? (object)['id' => 82],
        'content' => "Don't sleep on @player:Rymazing though, 148 elims on Map 1! The whole team was firing on all cylinders",
        'created_at' => now()->subMinutes(41)
    ],
    [
        'parent' => $commentIds[1],
        'user' => $users[0] ?? (object)['id' => 1],
        'content' => "True, but Sentinels came back strong on Map 3. This series could go either way!",
        'created_at' => now()->subMinutes(38)
    ],
    [
        'parent' => $commentIds[3],
        'user' => $users[5] ?? (object)['id' => 83],
        'content' => "The live scoring updates are so smooth! Love watching the stats update in real-time 📊",
        'created_at' => now()->subMinutes(18)
    ],
    [
        'parent' => $commentIds[3],
        'user' => $users[1] ?? (object)['id' => 2],
        'content' => "LETS GO SENTINELS! One more map for the W! 🎯",
        'created_at' => now()->subMinutes(17)
    ],
    [
        'parent' => $commentIds[5],
        'user' => $users[2] ?? (object)['id' => 3],
        'content' => "Nah, Sentinels got this 3-1. They're looking too strong on these control maps",
        'created_at' => now()->subMinutes(8)
    ],
    [
        'parent' => $commentIds[5],
        'user' => $users[0] ?? (object)['id' => 1],
        'content' => "It's anyone's game at this point. Both teams showing why they're top tier!",
        'created_at' => now()->subMinutes(5)
    ]
];

foreach ($replies as $reply) {
    DB::table('match_comments')->insert([
        'match_id' => $matchId,
        'user_id' => $reply['user']->id,
        'content' => $reply['content'],
        'parent_id' => $reply['parent'],
        'upvotes' => rand(2, 25),
        'downvotes' => rand(0, 3),
        'is_edited' => false,
        'created_at' => $reply['created_at'],
        'updated_at' => $reply['created_at']
    ]);
    echo "  ✓ Added reply from user {$reply['user']->id}\n";
}

// Add some nested replies (replies to replies)
$nestedReplies = [
    [
        'parent' => $commentIds[0] + 7, // Reply to first reply
        'user' => $users[3] ?? (object)['id' => 81],
        'content' => "The stats don't lie! This team is on fire 🔥🔥",
        'created_at' => now()->subMinutes(40)
    ],
    [
        'parent' => $commentIds[0] + 8, // Reply to second reply
        'user' => $users[5] ?? (object)['id' => 83],
        'content' => "Whole team diff on Map 1 for sure",
        'created_at' => now()->subMinutes(39)
    ]
];

foreach ($nestedReplies as $reply) {
    DB::table('match_comments')->insert([
        'match_id' => $matchId,
        'user_id' => $reply['user']->id,
        'content' => $reply['content'],
        'parent_id' => $reply['parent'],
        'upvotes' => rand(1, 15),
        'downvotes' => rand(0, 2),
        'is_edited' => false,
        'created_at' => $reply['created_at'],
        'updated_at' => $reply['created_at']
    ]);
    echo "  ✓ Added nested reply from user {$reply['user']->id}\n";
}

// Parse and store mentions
$allComments = array_merge($comments, $replies, $nestedReplies);
foreach ($allComments as $comment) {
    // Extract mentions
    preg_match_all('/@(player|team):([^\s]+)/i', $comment['content'], $matches);
    
    if (!empty($matches[0])) {
        foreach ($matches[0] as $index => $mention) {
            $type = strtolower($matches[1][$index]);
            $name = $matches[2][$index];
            
            $entityId = null;
            $entityType = null;
            
            if ($type === 'player') {
                $player = DB::table('players')->where('name', 'like', '%' . $name . '%')->first();
                if ($player) {
                    $entityId = $player->id;
                    $entityType = 'player';
                }
            } elseif ($type === 'team') {
                $team = DB::table('teams')->where('name', 'like', '%' . $name . '%')->first();
                if ($team) {
                    $entityId = $team->id;
                    $entityType = 'team';
                }
            }
            
            if ($entityId) {
                DB::table('mentions')->insert([
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'mentioned_by' => $comment['user']->id,
                    'context_type' => 'match_comment',
                    'context_id' => $matchId,
                    'mention_text' => $mention,
                    'created_at' => $comment['created_at'],
                    'updated_at' => $comment['created_at']
                ]);
            }
        }
    }
}

$totalComments = DB::table('match_comments')->where('match_id', $matchId)->count();

echo "\n✅ Successfully added to Match 13:\n";
echo "  - {$totalComments} total comments (with replies)\n";
echo "  - Stream URL: Twitch\n";
echo "  - VOD URL: YouTube\n";
echo "  - Betting URL: Bet365\n";
echo "\nView the match: https://staging.mrvl.net/#match-detail/13\n";