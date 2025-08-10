<?php
/**
 * Fix Player Controller Data Structure
 * This script patches the player API responses to be compatible with frontend expectations
 */

require_once 'vendor/autoload.php';

// Initialize Laravel app
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Player;
use Illuminate\Support\Facades\DB;

echo "🔧 Fixing Player Controller Data Structure...\n\n";

// Test the current issue
echo "📋 Current Player Data Structure Issues:\n";
echo "=====================================\n";

// Fetch a sample player using raw query (like the current controller)
$rawPlayer = DB::table('players as p')
    ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
    ->select([
        'p.id', 'p.username', 'p.real_name', 'p.role', 'p.avatar', 
        'p.rating', 'p.main_hero', 'p.country', 'p.age', 'p.status',
        't.name as team_name', 't.short_name as team_short', 't.logo as team_logo'
    ])
    ->first();

if ($rawPlayer) {
    echo "❌ Current structure missing 'name' field (has 'username')\n";
    echo "❌ Current structure missing direct 'team_id' field\n";
    echo "❌ Team data is flattened, not nested as expected\n";
    
    echo "\nCurrent fields: " . implode(', ', array_keys((array)$rawPlayer)) . "\n";
} else {
    echo "❌ No player data found\n";
}

// Now let's create the fix
echo "\n🔧 Implementing Data Structure Fix...\n";
echo "=====================================\n";

// Create a test API response transformer function
function transformPlayerForAPI($player) {
    // Get team data if player has team_id
    $teamData = null;
    if ($player->team_id) {
        $teamData = DB::table('teams')->where('id', $player->team_id)->first();
    }
    
    return [
        'id' => $player->id,
        'name' => $player->username, // Frontend expects 'name', we have 'username'
        'username' => $player->username, // Keep original for compatibility
        'real_name' => $player->real_name,
        'team_id' => $player->team_id, // Frontend expects direct team_id
        'team' => $teamData ? [
            'id' => $teamData->id,
            'name' => $teamData->name,
            'short_name' => $teamData->short_name,
            'logo' => $teamData->logo,
            'logo_exists' => !empty($teamData->logo) && $teamData->logo !== '/images/team-placeholder.svg',
            'logo_fallback' => [
                'text' => substr($teamData->name, 0, 3),
                'color' => '#' . substr(md5($teamData->name), 0, 6),
                'type' => 'team-logo'
            ]
        ] : null,
        'role' => $player->role,
        'avatar' => $player->avatar ?: '/images/player-placeholder.svg',
        'avatar_exists' => !empty($player->avatar) && $player->avatar !== '/images/player-placeholder.svg',
        'avatar_fallback' => [
            'text' => substr($player->username, 0, 2),
            'color' => '#' . substr(md5($player->username), 0, 6),
            'type' => 'player-avatar'
        ],
        'rating' => $player->rating ?: 1200,
        'elo_rating' => $player->elo_rating ?: $player->rating ?: 1200,
        'main_hero' => $player->main_hero ?: 'Spider-Man',
        'country' => $player->country,
        'flag' => $player->flag ?: ($player->country ? '🏳️' : null),
        'age' => $player->age,
        'status' => $player->status ?: 'active',
        'earnings' => $player->earnings ?: 0,
        'social_media' => is_string($player->social_media) ? 
            json_decode($player->social_media, true) : 
            ($player->social_media ?: []),
        'achievements' => is_string($player->achievements) ? 
            json_decode($player->achievements, true) : 
            ($player->achievements ?: []),
        'created_at' => $player->created_at,
        'updated_at' => $player->updated_at
    ];
}

// Test the transformation with a real player
$testPlayer = DB::table('players')->first();

if ($testPlayer) {
    $transformedPlayer = transformPlayerForAPI($testPlayer);
    
    echo "✅ Transformation successful!\n";
    echo "✅ Added 'name' field: " . $transformedPlayer['name'] . "\n";
    echo "✅ Added 'team_id' field: " . ($transformedPlayer['team_id'] ?: 'null') . "\n";
    echo "✅ Nested team object: " . (isset($transformedPlayer['team']) ? 'present' : 'null') . "\n";
    
    echo "\n📋 Transformed Player Structure:\n";
    foreach ($transformedPlayer as $key => $value) {
        if (is_array($value)) {
            echo "• $key: " . json_encode($value) . "\n";
        } else {
            echo "• $key: $value\n";
        }
    }
    
} else {
    echo "❌ No player data available for testing\n";
}

// Create a patch file for the PlayerController
$patchContent = '<?php
/**
 * Patched PlayerController methods for frontend compatibility
 * Replace the existing index() and show() methods with these
 */

// For PlayerController@index method - replace the current method with this:
public function index(Request $request)
{
    try {
        $query = Player::with([\'team\' => function($q) {
            $q->select(\'id\', \'name\', \'short_name\', \'logo\');
        }]);
        
        // Apply filters
        if ($request->role && $request->role !== \'all\') {
            $query->where(\'role\', $request->role);
        }
        if ($request->team && $request->team !== \'all\') {
            $query->where(\'team_id\', $request->team);
        }
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where(\'username\', \'LIKE\', \'%\' . $request->search . \'%\')
                  ->orWhere(\'real_name\', \'LIKE\', \'%\' . $request->search . \'%\');
            });
        }
        
        $perPage = min($request->get(\'per_page\', 50), 100);
        $players = $query->orderBy(\'rating\', \'desc\')->paginate($perPage);
        
        // Transform each player for frontend compatibility
        $transformedPlayers = $players->getCollection()->map(function($player) {
            return [
                \'id\' => $player->id,
                \'name\' => $player->username, // Frontend expects \'name\'
                \'username\' => $player->username,
                \'real_name\' => $player->real_name,
                \'team_id\' => $player->team_id, // Frontend expects direct team_id
                \'team\' => $player->team ? [
                    \'id\' => $player->team->id,
                    \'name\' => $player->team->name,
                    \'short_name\' => $player->team->short_name,
                    \'logo\' => $player->team->logo ?: \'/images/team-placeholder.svg\',
                    \'logo_exists\' => !empty($player->team->logo) && $player->team->logo !== \'/images/team-placeholder.svg\',
                    \'logo_fallback\' => [
                        \'text\' => substr($player->team->name, 0, 3),
                        \'color\' => \'#\' . substr(md5($player->team->name), 0, 6),
                        \'type\' => \'team-logo\'
                    ]
                ] : null,
                \'role\' => $player->role,
                \'avatar\' => $player->avatar ?: \'/images/player-placeholder.svg\',
                \'avatar_exists\' => !empty($player->avatar) && $player->avatar !== \'/images/player-placeholder.svg\',
                \'avatar_fallback\' => [
                    \'text\' => substr($player->username, 0, 2),
                    \'color\' => \'#\' . substr(md5($player->username), 0, 6),
                    \'type\' => \'player-avatar\'
                ],
                \'rating\' => $player->rating ?: 1200,
                \'elo_rating\' => $player->elo_rating ?: $player->rating ?: 1200,
                \'main_hero\' => $player->main_hero ?: \'Spider-Man\',
                \'country\' => $player->country,
                \'flag\' => $player->flag ?: ($player->country ? \'🏳️\' : null),
                \'age\' => $player->age,
                \'status\' => $player->status ?: \'active\',
                \'earnings\' => $player->earnings ?: 0,
                \'social_media\' => is_string($player->social_media) ? 
                    json_decode($player->social_media, true) : 
                    ($player->social_media ?: []),
                \'created_at\' => $player->created_at,
                \'updated_at\' => $player->updated_at
            ];
        });
        
        // Replace the collection with transformed data
        $players->setCollection($transformedPlayers);
        
        return response()->json([
            \'success\' => true,
            \'data\' => $players->items(),
            \'pagination\' => [
                \'current_page\' => $players->currentPage(),
                \'last_page\' => $players->lastPage(),
                \'per_page\' => $players->perPage(),
                \'total\' => $players->total()
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            \'success\' => false,
            \'message\' => \'Failed to fetch players\',
            \'error\' => $e->getMessage()
        ], 500);
    }
}

// For PlayerController@show method - replace the current method with this:
public function show($id)
{
    try {
        $player = Player::with([\'team\', \'teamHistory\', \'matchStats\'])
            ->findOrFail($id);
        
        // Transform for frontend compatibility
        $transformedPlayer = [
            \'id\' => $player->id,
            \'name\' => $player->username, // Frontend expects \'name\'
            \'username\' => $player->username,
            \'real_name\' => $player->real_name,
            \'team_id\' => $player->team_id, // Frontend expects direct team_id
            \'team\' => $player->team ? [
                \'id\' => $player->team->id,
                \'name\' => $player->team->name,
                \'short_name\' => $player->team->short_name,
                \'logo\' => $player->team->logo ?: \'/images/team-placeholder.svg\',
                \'earnings\' => $player->team->earnings ?: 0,
                \'region\' => $player->team->region,
                \'country\' => $player->team->country
            ] : null,
            \'role\' => $player->role,
            \'avatar\' => $player->avatar ?: \'/images/player-placeholder.svg\',
            \'rating\' => $player->rating ?: 1200,
            \'elo_rating\' => $player->elo_rating ?: $player->rating ?: 1200,
            \'peak_rating\' => $player->peak_rating ?: $player->rating ?: 1200,
            \'main_hero\' => $player->main_hero ?: \'Spider-Man\',
            \'alt_heroes\' => is_string($player->alt_heroes) ? 
                json_decode($player->alt_heroes, true) : 
                ($player->alt_heroes ?: []),
            \'country\' => $player->country,
            \'nationality\' => $player->nationality ?: $player->country,
            \'flag\' => $player->flag,
            \'age\' => $player->age,
            \'birth_date\' => $player->birth_date,
            \'status\' => $player->status ?: \'active\',
            \'earnings\' => $player->earnings ?: 0,
            \'total_earnings\' => $player->total_earnings ?: $player->earnings ?: 0,
            \'social_media\' => is_string($player->social_media) ? 
                json_decode($player->social_media, true) : 
                ($player->social_media ?: []),
            \'achievements\' => is_string($player->achievements) ? 
                json_decode($player->achievements, true) : 
                ($player->achievements ?: []),
            \'biography\' => $player->biography,
            \'liquipedia_url\' => $player->liquipedia_url,
            \'created_at\' => $player->created_at,
            \'updated_at\' => $player->updated_at
        ];
        
        return response()->json([
            \'success\' => true,
            \'data\' => $transformedPlayer
        ]);
        
    } catch (ModelNotFoundException $e) {
        return response()->json([
            \'success\' => false,
            \'message\' => \'Player not found\'
        ], 404);
    } catch (Exception $e) {
        return response()->json([
            \'success\' => false,
            \'message\' => \'Failed to fetch player\',
            \'error\' => $e->getMessage()
        ], 500);
    }
}
';

file_put_contents('player_controller_patch.php', $patchContent);

echo "\n✅ Data structure analysis complete!\n";
echo "📄 Patch file created: player_controller_patch.php\n";
echo "\n🚀 Next Steps:\n";
echo "1. Apply the patch to PlayerController.php\n";
echo "2. Test the updated API endpoints\n";
echo "3. Verify frontend compatibility\n";

echo "\n=====================================\n";
echo "🎯 SUMMARY\n";
echo "=====================================\n";
echo "• Identified root cause of frontend-backend incompatibility\n";
echo "• Created data transformation solution\n";
echo "• Generated patch for PlayerController methods\n";
echo "• Maintains backward compatibility while fixing frontend issues\n";