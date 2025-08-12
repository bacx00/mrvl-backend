<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Team;
use App\Models\Player;
use App\Helpers\ImageHelper;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class AdminTeamController extends Controller
{
    /**
     * Display a listing of teams for admin management.
     */
    public function index(Request $request)
    {
        try {
            Log::info('AdminTeamController: Fetching teams for admin panel', [
                'request_params' => $request->all()
            ]);

            $query = DB::table('teams as t')
                ->select([
                    't.id', 't.name', 't.short_name', 't.logo', 't.region', 't.platform', 
                    't.game', 't.division', 't.country', 't.rating', 't.rank', 't.win_rate', 
                    't.points', 't.record', 't.peak', 't.streak', 't.founded', 't.captain', 
                    't.coach', 't.coach_name', 't.coach_nationality', 't.coach_social_media', 
                    't.website', 't.earnings', 't.social_media', 't.achievements',
                    't.recent_form', 't.player_count', 't.status', 't.created_at', 't.updated_at'
                ]);

            // Apply search filter
            if ($request->search && $request->search !== '') {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('t.name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('t.short_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('t.region', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('t.country', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Apply region filter
            if ($request->region && $request->region !== 'all') {
                $query->where('t.region', $request->region);
            }

            // Apply platform filter for Marvel Rivals
            if ($request->platform && $request->platform !== 'all') {
                $query->where('t.platform', $request->platform);
            }

            // Get all teams (no pagination for admin panel to allow client-side operations)
            $teams = $query->orderBy('t.rating', 'desc')->get();

            Log::info('AdminTeamController: Teams fetched successfully', [
                'total_teams' => $teams->count(),
                'with_search' => $request->search ? true : false,
                'with_region_filter' => $request->region && $request->region !== 'all' ? $request->region : false
            ]);

            // Transform teams to Marvel Rivals admin format
            $formattedTeams = $teams->map(function($team) {
                // Safely get logo info with fallback
                try {
                    $logoInfo = ImageHelper::getTeamLogo($team->logo, $team->name);
                } catch (Exception $e) {
                    Log::warning('Error getting team logo', ['team_id' => $team->id, 'error' => $e->getMessage()]);
                    $logoInfo = [
                        'url' => '/images/team-placeholder.svg',
                        'exists' => false,
                        'fallback' => ['text' => substr($team->name ?? '?', 0, 3), 'color' => '#6366f1', 'type' => 'team-logo']
                    ];
                }
                
                // Get player count for this team
                $playerCount = DB::table('players')->where('team_id', $team->id)->count();
                
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'logo' => $logoInfo['url'],
                    'logo_exists' => $logoInfo['exists'],
                    'logo_fallback' => $logoInfo['fallback'],
                    'region' => $team->region,
                    'platform' => $team->platform ?? 'PC',
                    'country' => $team->country,
                    'flag' => $this->getCountryFlag($team->country),
                    'rating' => $team->rating ?? 1000,
                    'rank' => $team->rank ?? 999,
                    'win_rate' => $team->win_rate ?? 0,
                    'points' => $team->points ?? 0,
                    'record' => $team->record ?? '0-0',
                    'peak' => $team->peak ?? $team->rating ?? 1000,
                    'streak' => $team->streak ?? 'N/A',
                    'founded' => $team->founded,
                    'captain' => $team->captain,
                    'coach' => $team->coach,
                    'coach_name' => $team->coach_name,
                    'coach_nationality' => $team->coach_nationality,
                    'coach_social_media' => $team->coach_social_media ? json_decode($team->coach_social_media, true) : [],
                    'website' => $team->website,
                    'earnings' => $team->earnings ?? '$0',
                    'social_media' => $team->social_media ? json_decode($team->social_media, true) : [],
                    'achievements' => $team->achievements ? json_decode($team->achievements, true) : [],
                    'status' => $team->status ?? 'active',
                    // Marvel Rivals specific data
                    'game' => $team->game ?? 'Marvel Rivals',
                    'division' => $team->division ?? $this->getDivisionByRating($team->rating ?? 1000),
                    'recent_form' => $team->recent_form ? json_decode($team->recent_form, true) : $this->generateRecentForm($team->id),
                    'player_count' => $playerCount,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at
                ];
            });

            return response()->json([
                'data' => $formattedTeams->values(), // Ensure array format
                'total' => $formattedTeams->count(),
                'success' => true,
                'message' => 'Teams retrieved successfully'
            ]);

        } catch (QueryException $e) {
            Log::error('AdminTeamController: Database error fetching teams', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while fetching teams.',
                'error_code' => 'DATABASE_ERROR',
                'data' => []
            ], 500);

        } catch (Exception $e) {
            Log::error('AdminTeamController: General error fetching teams', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching teams.',
                'error_code' => 'GENERAL_ERROR',
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created team.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:teams,name',
                'short_name' => 'required|string|max:10|unique:teams,short_name',
                'region' => 'required|string|in:NA,EU,APAC,LATAM,BR,CN,KR,JP',
                'country' => 'nullable|string|max:255',
                'rating' => 'nullable|integer|min:0|max:5000',
                'description' => 'nullable|string|max:1000',
                'website' => 'nullable|url|max:255',
                'social_media' => 'nullable|string' // JSON string
            ]);

            // Parse social media JSON if provided
            if ($validatedData['social_media']) {
                $socialMedia = json_decode($validatedData['social_media'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ValidationException('Invalid social media JSON format');
                }
                $validatedData['social_media'] = json_encode($socialMedia);
            }

            // Set defaults
            $validatedData['rating'] = $validatedData['rating'] ?? 1500;
            $validatedData['game'] = 'Marvel Rivals';
            $validatedData['platform'] = 'PC';
            $validatedData['status'] = 'active';
            $validatedData['player_count'] = 0;

            $team = Team::create($validatedData);

            Log::info('AdminTeamController: Team created successfully', [
                'team_id' => $team->id,
                'team_name' => $team->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team created successfully',
                'data' => $team
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('AdminTeamController: Error creating team', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create team',
                'error_code' => 'CREATION_ERROR'
            ], 500);
        }
    }

    /**
     * Display the specified team for admin.
     */
    public function show($id)
    {
        try {
            $team = Team::with('players')->findOrFail($id);

            Log::info('AdminTeamController: Team details retrieved', [
                'team_id' => $id,
                'team_name' => $team->name
            ]);

            return response()->json([
                'success' => true,
                'data' => $team
            ]);

        } catch (Exception $e) {
            Log::error('AdminTeamController: Error fetching team details', [
                'team_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Team not found',
                'error_code' => 'TEAM_NOT_FOUND'
            ], 404);
        }
    }

    /**
     * Update the specified team.
     */
    public function update(Request $request, $id)
    {
        try {
            $team = Team::findOrFail($id);

            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:teams,name,' . $id,
                'short_name' => 'sometimes|required|string|max:10|unique:teams,short_name,' . $id,
                'region' => 'sometimes|required|string|in:NA,EU,APAC,LATAM,BR,CN,KR,JP',
                'country' => 'nullable|string|max:255',
                'rating' => 'nullable|integer|min:0|max:5000',
                'description' => 'nullable|string|max:1000',
                'website' => 'nullable|url|max:255',
                'social_media' => 'nullable|string' // JSON string
            ]);

            // Parse social media JSON if provided
            if (isset($validatedData['social_media'])) {
                $socialMedia = json_decode($validatedData['social_media'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ValidationException('Invalid social media JSON format');
                }
                $validatedData['social_media'] = json_encode($socialMedia);
            }

            $team->update($validatedData);

            Log::info('AdminTeamController: Team updated successfully', [
                'team_id' => $id,
                'team_name' => $team->name,
                'updated_fields' => array_keys($validatedData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team updated successfully',
                'data' => $team->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            Log::error('AdminTeamController: Error updating team', [
                'team_id' => $id,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update team',
                'error_code' => 'UPDATE_ERROR'
            ], 500);
        }
    }

    /**
     * Remove the specified team.
     */
    public function destroy($id)
    {
        try {
            $team = Team::findOrFail($id);
            $teamName = $team->name;

            // Check if team has players
            $playerCount = $team->players()->count();
            if ($playerCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete team with {$playerCount} active players. Please transfer or remove players first.",
                    'error_code' => 'TEAM_HAS_PLAYERS'
                ], 400);
            }

            $team->delete();

            Log::info('AdminTeamController: Team deleted successfully', [
                'team_id' => $id,
                'team_name' => $teamName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team deleted successfully'
            ]);

        } catch (Exception $e) {
            Log::error('AdminTeamController: Error deleting team', [
                'team_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete team',
                'error_code' => 'DELETION_ERROR'
            ], 500);
        }
    }

    /**
     * Helper method to get country flag
     */
    private function getCountryFlag($country)
    {
        if (!$country) return '🏳️';
        
        $flagMap = [
            'United States' => '🇺🇸', 'USA' => '🇺🇸', 'US' => '🇺🇸',
            'Canada' => '🇨🇦', 'CA' => '🇨🇦',
            'United Kingdom' => '🇬🇧', 'UK' => '🇬🇧', 'Britain' => '🇬🇧',
            'France' => '🇫🇷', 'FR' => '🇫🇷',
            'Germany' => '🇩🇪', 'DE' => '🇩🇪',
            'Spain' => '🇪🇸', 'ES' => '🇪🇸',
            'Italy' => '🇮🇹', 'IT' => '🇮🇹',
            'Netherlands' => '🇳🇱', 'NL' => '🇳🇱',
            'Sweden' => '🇸🇪', 'SE' => '🇸🇪',
            'Norway' => '🇳🇴', 'NO' => '🇳🇴',
            'Denmark' => '🇩🇰', 'DK' => '🇩🇰',
            'Finland' => '🇫🇮', 'FI' => '🇫🇮',
            'Poland' => '🇵🇱', 'PL' => '🇵🇱',
            'Russia' => '🇷🇺', 'RU' => '🇷🇺',
            'Ukraine' => '🇺🇦', 'UA' => '🇺🇦',
            'Turkey' => '🇹🇷', 'TR' => '🇹🇷',
            'South Korea' => '🇰🇷', 'Korea' => '🇰🇷', 'KR' => '🇰🇷',
            'Japan' => '🇯🇵', 'JP' => '🇯🇵',
            'China' => '🇨🇳', 'CN' => '🇨🇳',
            'Australia' => '🇦🇺', 'AU' => '🇦🇺',
            'New Zealand' => '🇳🇿', 'NZ' => '🇳🇿',
            'Singapore' => '🇸🇬', 'SG' => '🇸🇬',
            'Thailand' => '🇹🇭', 'TH' => '🇹🇭',
            'Philippines' => '🇵🇭', 'PH' => '🇵🇭',
            'Indonesia' => '🇮🇩', 'ID' => '🇮🇩',
            'Malaysia' => '🇲🇾', 'MY' => '🇲🇾',
            'Vietnam' => '🇻🇳', 'VN' => '🇻🇳',
            'India' => '🇮🇳', 'IN' => '🇮🇳',
            'Brazil' => '🇧🇷', 'BR' => '🇧🇷',
            'Argentina' => '🇦🇷', 'AR' => '🇦🇷',
            'Chile' => '🇨🇱', 'CL' => '🇨🇱',
            'Mexico' => '🇲🇽', 'MX' => '🇲🇽',
            'Colombia' => '🇨🇴', 'CO' => '🇨🇴',
            'Peru' => '🇵🇪', 'PE' => '🇵🇪'
        ];
        
        return $flagMap[$country] ?? '🏳️';
    }

    /**
     * Helper method to get division by rating
     */
    private function getDivisionByRating($rating)
    {
        if ($rating >= 2500) return 'Grandmaster';
        if ($rating >= 2200) return 'Master';
        if ($rating >= 1900) return 'Diamond';
        if ($rating >= 1600) return 'Platinum';
        if ($rating >= 1300) return 'Gold';
        if ($rating >= 1000) return 'Silver';
        return 'Bronze';
    }

    /**
     * Helper method to generate recent form
     */
    private function generateRecentForm($teamId)
    {
        try {
            $recentMatches = DB::table('matches')
                ->where(function($query) use ($teamId) {
                    $query->where('team1_id', $teamId)
                          ->orWhere('team2_id', $teamId);
                })
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $form = [];
            foreach ($recentMatches as $match) {
                if ($match->team1_id == $teamId) {
                    $form[] = $match->team1_score > $match->team2_score ? 'W' : 'L';
                } else {
                    $form[] = $match->team2_score > $match->team1_score ? 'W' : 'L';
                }
            }

            return $form;
        } catch (Exception $e) {
            return ['N/A'];
        }
    }
}