<?php
require_once __DIR__ . '/vendor/autoload.php';
use Illuminate\Support\Facades\DB;
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🎮 Adding comments and URLs to Match 13 via tinker...\n\n";

$matchId = 13;

// Update match with URLs
echo "📺 Adding URLs to match...\n";
DB::table('matches')->where('id', $matchId)->update([
    'stream_url' => 'https://www.twitch.tv/marvel_rivals',
    'vod_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'betting_url' => 'https://www.bet365.com/esports/marvel-rivals/sentinels-vs-nrg',
    'updated_at' => now()
]);

echo "✅ URLs added successfully\n\n";

// Get user IDs for comments (using Jhonny and creating variety)
$userId = 1; // Jhonny's ID

echo "💬 Creating comments...\n";

// Parent comments
$comment1Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'SENTINELS LOOKING STRONG! 🔥 That Map 1 performance was insane, especially Coluge with the Spider-Man plays!',
    'parent_id' => null,
    'upvotes' => rand(15, 50),
    'downvotes' => rand(0, 5),
    'is_edited' => false,
    'created_at' => now()->subMinutes(45),
    'updated_at' => now()->subMinutes(45)
]);

$comment2Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'NRG bounced back hard on Map 2 though. Titan absolutely dominated with 134 eliminations across 5 heroes! This BO5 is going to be legendary 🎮',
    'parent_id' => null,
    'upvotes' => rand(10, 40),
    'downvotes' => rand(0, 3),
    'is_edited' => false,
    'created_at' => now()->subMinutes(40),
    'updated_at' => now()->subMinutes(40)
]);

$comment3Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'Anyone else notice how many hero switches we are seeing? This meta is so diverse! Every player switching between 5 different heroes per map is wild',
    'parent_id' => null,
    'upvotes' => rand(8, 35),
    'downvotes' => rand(0, 4),
    'is_edited' => false,
    'created_at' => now()->subMinutes(35),
    'updated_at' => now()->subMinutes(35)
]);

$comment4Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'Map 3 is LIVE right now and it is neck and neck! Sentinels needs this map to secure match point. The pressure is real! 💪',
    'parent_id' => null,
    'upvotes' => rand(20, 45),
    'downvotes' => rand(0, 2),
    'is_edited' => false,
    'created_at' => now()->subMinutes(20),
    'updated_at' => now()->subMinutes(20)
]);

$comment5Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'The hero diversity in this match is incredible. Seeing players flex between DPS, Tank, and Support roles shows true skill. This is peak Marvel Rivals gameplay!',
    'parent_id' => null,
    'upvotes' => rand(12, 38),
    'downvotes' => rand(0, 3),
    'is_edited' => false,
    'created_at' => now()->subMinutes(15),
    'updated_at' => now()->subMinutes(15)
]);

$comment6Id = DB::table('match_comments')->insertGetId([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'Predictions for Map 4 and 5? I think NRG takes it 3-2 in the reverse sweep. They have momentum after that Map 2 domination!',
    'parent_id' => null,
    'upvotes' => rand(5, 25),
    'downvotes' => rand(2, 8),
    'is_edited' => false,
    'created_at' => now()->subMinutes(10),
    'updated_at' => now()->subMinutes(10)
]);

echo "✅ Added 6 parent comments\n\n";

// Add replies
echo "💬 Adding replies...\n";

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => "Facts! Coluge's movement was insane. That 132 elimination game across 5 heroes is MVP worthy 🏆",
    'parent_id' => $comment1Id,
    'upvotes' => rand(5, 20),
    'downvotes' => rand(0, 2),
    'is_edited' => false,
    'created_at' => now()->subMinutes(42),
    'updated_at' => now()->subMinutes(42)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => "Don't sleep on Rymazing though, 148 elims on Map 1! The whole team was firing on all cylinders",
    'parent_id' => $comment1Id,
    'upvotes' => rand(3, 18),
    'downvotes' => rand(0, 1),
    'is_edited' => false,
    'created_at' => now()->subMinutes(41),
    'updated_at' => now()->subMinutes(41)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'True, but Sentinels came back strong on Map 3. This series could go either way!',
    'parent_id' => $comment2Id,
    'upvotes' => rand(4, 15),
    'downvotes' => rand(0, 2),
    'is_edited' => false,
    'created_at' => now()->subMinutes(38),
    'updated_at' => now()->subMinutes(38)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'The live scoring updates are so smooth! Love watching the stats update in real-time 📊',
    'parent_id' => $comment4Id,
    'upvotes' => rand(8, 22),
    'downvotes' => rand(0, 1),
    'is_edited' => false,
    'created_at' => now()->subMinutes(18),
    'updated_at' => now()->subMinutes(18)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => 'LETS GO SENTINELS! One more map for the W! 🎯',
    'parent_id' => $comment4Id,
    'upvotes' => rand(10, 30),
    'downvotes' => rand(0, 3),
    'is_edited' => false,
    'created_at' => now()->subMinutes(17),
    'updated_at' => now()->subMinutes(17)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => "Nah, Sentinels got this 3-1. They're looking too strong on these control maps",
    'parent_id' => $comment6Id,
    'upvotes' => rand(6, 18),
    'downvotes' => rand(1, 4),
    'is_edited' => false,
    'created_at' => now()->subMinutes(8),
    'updated_at' => now()->subMinutes(8)
]);

DB::table('match_comments')->insert([
    'match_id' => $matchId,
    'user_id' => $userId,
    'content' => "It's anyone's game at this point. Both teams showing why they're top tier!",
    'parent_id' => $comment6Id,
    'upvotes' => rand(7, 20),
    'downvotes' => rand(0, 2),
    'is_edited' => false,
    'created_at' => now()->subMinutes(5),
    'updated_at' => now()->subMinutes(5)
]);

echo "✅ Added 7 replies\n\n";

// Get total comment count
$totalComments = DB::table('match_comments')->where('match_id', $matchId)->count();

echo "✅ Successfully added to Match 13:\n";
echo "  - {$totalComments} total comments (with replies)\n";
echo "  - Stream URL: Twitch\n";
echo "  - VOD URL: YouTube\n";
echo "  - Betting URL: Bet365\n\n";
echo "🔗 View the match: https://staging.mrvl.net/#match-detail/13\n";