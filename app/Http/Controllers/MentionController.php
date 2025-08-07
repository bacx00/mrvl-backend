<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MentionController extends Controller
{
    public function searchMentions(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'q' => 'nullable|string|max:100',
                'type' => 'nullable|in:all,user,team,player',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            $query = $validated['q'] ?? '';
            $type = $validated['type'] ?? 'all';
            $limit = min($validated['limit'] ?? 10, 20); // Max 20 results

            if (strlen($query) < 1) {
                // Return popular suggestions when no query provided
                return $this->getPopularMentions($request);
            }
        
        // For very short queries, be more restrictive
        if (strlen($query) === 1 && $type === 'all') {
            $limit = min($limit, 5); // Limit single character searches
        }

        $results = [];

        // Search users if type is 'all' or 'user'
        if ($type === 'all' || $type === 'user') {
            $users = DB::table('users')
                ->where('name', 'LIKE', "%{$query}%")
                ->where('status', 'active')
                ->select(['id', 'name', 'avatar'])
                ->limit($limit)
                ->get();

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];
            }
        }

        // Search teams if type is 'all' or 'team'
        if ($type === 'all' || $type === 'team') {
            $teams = DB::table('teams')
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('short_name', 'LIKE', "%{$query}%");
                })
                ->select(['id', 'name', 'short_name', 'logo', 'region'])
                ->limit($limit)
                ->get();

            foreach ($teams as $team) {
                $results[] = [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];
            }
        }

        // Search players if type is 'all' or 'player'
        if ($type === 'all' || $type === 'player') {
            $players = DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                ->where(function($q) use ($query) {
                    $q->where('p.username', 'LIKE', "%{$query}%")
                      ->orWhere('p.real_name', 'LIKE', "%{$query}%");
                })
                ->select([
                    'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role',
                    't.name as team_name', 't.short_name as team_short'
                ])
                ->limit($limit)
                ->get();

            foreach ($players as $player) {
                $subtitle = $player->role;
                if ($player->team_name) {
                    $subtitle .= " • {$player->team_name}";
                }

                $results[] = [
                    'id' => $player->id,
                    'type' => 'player',
                    'name' => $player->username,
                    'display_name' => $player->real_name ?: $player->username,
                    'mention_text' => "@player:{$player->username}",
                    'avatar' => $player->avatar,
                    'subtitle' => $subtitle,
                    'icon' => 'player'
                ];
            }
        }

        // Sort results by relevance (exact matches first, then by name length)
        usort($results, function($a, $b) use ($query) {
            $aExact = stripos($a['name'], $query) === 0 ? 0 : 1;
            $bExact = stripos($b['name'], $query) === 0 ? 0 : 1;
            
            if ($aExact !== $bExact) {
                return $aExact - $bExact;
            }
            
            return strlen($a['name']) - strlen($b['name']);
        });

        // Limit total results
        $results = array_slice($results, 0, $limit);

        return response()->json([
            'data' => $results,
            'success' => true,
            'query' => $query,
            'type' => $type,
            'total_results' => count($results)
        ]);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('MentionController@searchMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error searching mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPopularMentions(Request $request)
    {
        try {
            $validated = $request->validate([
                'limit' => 'nullable|integer|min:1|max:50'
            ]);
            
            $limit = min($validated['limit'] ?? 10, 20);
        
        // Get most mentioned entities from the last 30 days
        $popularMentions = DB::table('mentions as m')
            ->select([
                'm.mentioned_type',
                'm.mentioned_id',
                DB::raw('COUNT(*) as mention_count')
            ])
            ->where('m.mentioned_at', '>=', now()->subDays(30))
            ->groupBy(['m.mentioned_type', 'm.mentioned_id'])
            ->orderBy('mention_count', 'desc')
            ->limit($limit)
            ->get();

        $results = [];

        foreach ($popularMentions as $mention) {
            $entityData = $this->getEntityData($mention->mentioned_type, $mention->mentioned_id);
            if ($entityData) {
                $entityData['mention_count'] = $mention->mention_count;
                $results[] = $entityData;
            }
        }

        // If no popular mentions, show some default suggestions
        if (empty($results)) {
            // Get some recent users
            $users = DB::table('users')
                ->where('status', 'active')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];
            }

            // Get some teams
            $teams = DB::table('teams')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            
            foreach ($teams as $team) {
                $results[] = [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];
            }
        }

        return response()->json([
            'data' => $results,
            'success' => true,
            'total_results' => count($results)
        ]);
        
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('MentionController@getPopularMentions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching popular mentions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getEntityData($type, $id)
    {
        switch ($type) {
            case 'user':
                $user = DB::table('users')
                    ->where('id', $id)
                    ->where('status', 'active')
                    ->select(['id', 'name', 'avatar'])
                    ->first();
                
                if (!$user) return null;
                
                return [
                    'id' => $user->id,
                    'type' => 'user',
                    'name' => $user->name,
                    'display_name' => $user->name,
                    'mention_text' => "@{$user->name}",
                    'avatar' => $user->avatar,
                    'subtitle' => 'User',
                    'icon' => 'user'
                ];

            case 'team':
                $team = DB::table('teams')
                    ->where('id', $id)
                    ->select(['id', 'name', 'short_name', 'logo', 'region'])
                    ->first();
                
                if (!$team) return null;
                
                return [
                    'id' => $team->id,
                    'type' => 'team',
                    'name' => $team->short_name,
                    'display_name' => $team->name,
                    'mention_text' => "@team:{$team->short_name}",
                    'avatar' => $team->logo,
                    'subtitle' => "Team • {$team->region}",
                    'icon' => 'team'
                ];

            case 'player':
                $player = DB::table('players as p')
                    ->leftJoin('teams as t', 'p.team_id', '=', 't.id')
                    ->where('p.id', $id)
                    ->select([
                        'p.id', 'p.username', 'p.real_name', 'p.avatar', 'p.role',
                        't.name as team_name'
                    ])
                    ->first();
                
                if (!$player) return null;
                
                $subtitle = $player->role;
                if ($player->team_name) {
                    $subtitle .= " • {$player->team_name}";
                }
                
                return [
                    'id' => $player->id,
                    'type' => 'player',
                    'name' => $player->username,
                    'display_name' => $player->real_name ?: $player->username,
                    'mention_text' => "@player:{$player->username}",
                    'avatar' => $player->avatar,
                    'subtitle' => $subtitle,
                    'icon' => 'player'
                ];

            default:
                return null;
        }
    }
}