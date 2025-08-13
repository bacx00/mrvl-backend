<?php

namespace App\Services;

use App\Models\MatchModel;
use App\Models\Event;
use App\Models\Team;
use App\Models\User;
use App\Models\MatchMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdvancedMatchManagementService
{
    protected $liveUpdateService;
    
    public function __construct(TournamentLiveUpdateService $liveUpdateService)
    {
        $this->liveUpdateService = $liveUpdateService;
    }

    /**
     * Create match with advanced scheduling and conflict detection
     */
    public function createMatchWithScheduling(array $matchData): MatchModel
    {
        DB::beginTransaction();
        
        try {
            // Conflict detection
            $conflicts = $this->detectSchedulingConflicts($matchData);
            if (!empty($conflicts)) {
                throw new \Exception('Scheduling conflicts detected: ' . implode(', ', $conflicts));
            }
            
            $match = MatchModel::create([
                'team1_id' => $matchData['team1_id'],
                'team2_id' => $matchData['team2_id'],
                'event_id' => $matchData['event_id'],
                'format' => $matchData['format'],
                'status' => 'upcoming',
                'scheduled_at' => Carbon::parse($matchData['scheduled_at']),
                'round' => $matchData['round'] ?? 1,
                'bracket_position' => $matchData['bracket_position'] ?? 1,
                'stream_urls' => $matchData['stream_urls'] ?? [],
                'hero_bans_enabled' => $matchData['hero_bans_enabled'] ?? false,
                'map_pool' => $matchData['map_pool'] ?? [],
                'notes' => $matchData['notes'] ?? []
            ]);

            // Create maps for the match based on format
            $this->createMatchMaps($match, $matchData);
            
            // Schedule notifications
            $this->scheduleMatchNotifications($match);
            
            DB::commit();
            Log::info('Match created with advanced scheduling', ['match_id' => $match->id]);
            
            return $match;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create match: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reschedule match with notification system
     */
    public function rescheduleMatch(MatchModel $match, Carbon $newDateTime, string $reason = ''): bool
    {
        DB::beginTransaction();
        
        try {
            $oldDateTime = $match->scheduled_at;
            
            // Check for conflicts with new time
            $conflicts = $this->detectSchedulingConflicts([
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'scheduled_at' => $newDateTime,
                'exclude_match_id' => $match->id
            ]);
            
            if (!empty($conflicts)) {
                throw new \Exception('Rescheduling conflicts detected: ' . implode(', ', $conflicts));
            }
            
            $match->update([
                'scheduled_at' => $newDateTime,
                'notes' => array_merge($match->notes ?? [], [
                    'reschedule' => [
                        'old_time' => $oldDateTime->toISOString(),
                        'new_time' => $newDateTime->toISOString(),
                        'reason' => $reason,
                        'rescheduled_at' => now()->toISOString()
                    ]
                ])
            ]);

            // Notify teams and followers
            $this->notifyMatchReschedule($match, $oldDateTime, $newDateTime, $reason);
            
            // Update scheduled notifications
            $this->scheduleMatchNotifications($match);
            
            DB::commit();
            Log::info('Match rescheduled', [
                'match_id' => $match->id,
                'old_time' => $oldDateTime,
                'new_time' => $newDateTime,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reschedule match: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Implement best-of-X match format support
     */
    public function setupMatchFormat(MatchModel $match, string $format): void
    {
        $formatConfig = $this->getFormatConfiguration($format);
        
        $match->update([
            'format' => $format,
            'map_pool' => $formatConfig['default_maps'] ?? [],
            'notes' => array_merge($match->notes ?? [], [
                'format_config' => $formatConfig
            ])
        ]);

        // Create appropriate number of maps
        $this->createMatchMaps($match, $formatConfig);
    }

    /**
     * Implement map veto/pick system
     */
    public function handleMapVetoPick(MatchModel $match, array $vetoPickData): array
    {
        $vetoProcess = [
            'process_type' => $vetoPickData['process_type'], // 'veto', 'pick', 'veto_pick'
            'map_pool' => $vetoPickData['map_pool'],
            'team_turn' => $vetoPickData['starting_team_id'] ?? $match->team1_id,
            'actions' => [],
            'final_maps' => [],
            'completed' => false
        ];

        // Process veto/pick action
        if (isset($vetoPickData['action'])) {
            $vetoProcess = $this->processVetoPickAction($match, $vetoProcess, $vetoPickData['action']);
        }

        // Update match with veto/pick data
        $match->update([
            'notes' => array_merge($match->notes ?? [], [
                'veto_pick_process' => $vetoProcess
            ])
        ]);

        // If process is complete, create final map list
        if ($vetoProcess['completed']) {
            $this->finalizeMatchMaps($match, $vetoProcess['final_maps']);
        }

        return $vetoProcess;
    }

    /**
     * Handle match protests and disputes
     */
    public function createMatchProtest(MatchModel $match, array $protestData): array
    {
        $protest = [
            'id' => uniqid('protest_'),
            'match_id' => $match->id,
            'protesting_team_id' => $protestData['team_id'],
            'category' => $protestData['category'], // 'rule_violation', 'technical_issue', 'cheating', 'other'
            'description' => $protestData['description'],
            'evidence' => $protestData['evidence'] ?? [],
            'status' => 'pending',
            'created_at' => now()->toISOString(),
            'admin_notes' => [],
            'resolution' => null
        ];

        // Update match with protest information
        $protests = $match->notes['protests'] ?? [];
        $protests[] = $protest;
        
        $match->update([
            'status' => 'disputed',
            'notes' => array_merge($match->notes ?? [], [
                'protests' => $protests
            ])
        ]);

        // Notify administrators
        $this->notifyAdminsOfProtest($match, $protest);

        Log::info('Match protest created', ['match_id' => $match->id, 'protest_id' => $protest['id']]);

        return $protest;
    }

    /**
     * Resolve match protest
     */
    public function resolveMatchProtest(MatchModel $match, string $protestId, array $resolution): bool
    {
        $protests = $match->notes['protests'] ?? [];
        $protestIndex = array_search($protestId, array_column($protests, 'id'));
        
        if ($protestIndex === false) {
            return false;
        }

        $protests[$protestIndex]['status'] = $resolution['status']; // 'upheld', 'dismissed'
        $protests[$protestIndex]['resolution'] = $resolution;
        $protests[$protestIndex]['resolved_at'] = now()->toISOString();
        $protests[$protestIndex]['resolved_by'] = $resolution['admin_id'];

        // Update match status
        $activeProtests = array_filter($protests, fn($p) => $p['status'] === 'pending');
        $newMatchStatus = empty($activeProtests) ? 'upcoming' : 'disputed';

        $match->update([
            'status' => $newMatchStatus,
            'notes' => array_merge($match->notes ?? [], [
                'protests' => $protests
            ])
        ]);

        // Apply any resolution actions
        if (isset($resolution['actions'])) {
            $this->applyProtestResolutionActions($match, $resolution['actions']);
        }

        Log::info('Match protest resolved', [
            'match_id' => $match->id,
            'protest_id' => $protestId,
            'resolution' => $resolution['status']
        ]);

        return true;
    }

    /**
     * Handle forfeit and walkover
     */
    public function handleForfeit(MatchModel $match, int $forfeitingTeamId, string $reason = ''): bool
    {
        DB::beginTransaction();
        
        try {
            $winningTeamId = $forfeitingTeamId === $match->team1_id ? $match->team2_id : $match->team1_id;
            
            $match->update([
                'status' => 'completed',
                'winner_id' => $winningTeamId,
                'ended_at' => now(),
                'team1_score' => $forfeitingTeamId === $match->team1_id ? 0 : 1,
                'team2_score' => $forfeitingTeamId === $match->team2_id ? 0 : 1,
                'notes' => array_merge($match->notes ?? [], [
                    'forfeit' => [
                        'forfeiting_team_id' => $forfeitingTeamId,
                        'reason' => $reason,
                        'forfeited_at' => now()->toISOString()
                    ]
                ])
            ]);

            // Update event standings
            $this->updateEventStandingsAfterForfeit($match, $forfeitingTeamId, $winningTeamId);
            
            // Notify relevant parties
            $this->notifyMatchForfeit($match, $forfeitingTeamId, $reason);
            
            DB::commit();
            Log::info('Match forfeit processed', [
                'match_id' => $match->id,
                'forfeiting_team' => $forfeitingTeamId,
                'reason' => $reason
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process forfeit: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Advanced match statistics and analytics
     */
    public function getMatchAnalytics(MatchModel $match): array
    {
        return [
            'basic_stats' => $this->getBasicMatchStats($match),
            'team_performance' => $this->getTeamPerformanceStats($match),
            'player_performance' => $this->getPlayerPerformanceStats($match),
            'map_statistics' => $this->getMapStatistics($match),
            'timeline' => $this->getMatchTimeline($match),
            'comparative_analysis' => $this->getComparativeAnalysis($match)
        ];
    }

    /**
     * Live match management
     */
    public function updateLiveMatchScore(MatchModel $match, array $scoreData): bool
    {
        if ($match->status !== 'live') {
            return false;
        }

        DB::beginTransaction();
        
        try {
            // Update overall match score
            $match->update([
                'team1_score' => $scoreData['team1_score'],
                'team2_score' => $scoreData['team2_score'],
                'maps_won_team1' => $scoreData['maps_won_team1'] ?? $match->maps_won_team1,
                'maps_won_team2' => $scoreData['maps_won_team2'] ?? $match->maps_won_team2
            ]);

            // Update current map if provided
            if (isset($scoreData['current_map'])) {
                $this->updateCurrentMapScore($match, $scoreData['current_map']);
            }

            // Check if match should be completed
            $this->checkMatchCompletion($match);

            // Broadcast live update
            $this->liveUpdateService->broadcastMatchUpdate($match, 'score_updated', $scoreData);
            
            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update live match score: ' . $e->getMessage());
            return false;
        }
    }

    // Helper methods
    private function detectSchedulingConflicts(array $matchData): array
    {
        $conflicts = [];
        $scheduledTime = Carbon::parse($matchData['scheduled_at']);
        
        // Check team conflicts (30-minute buffer)
        $conflictQuery = MatchModel::where(function($query) use ($matchData) {
            $query->where('team1_id', $matchData['team1_id'])
                  ->orWhere('team2_id', $matchData['team1_id'])
                  ->orWhere('team1_id', $matchData['team2_id'])
                  ->orWhere('team2_id', $matchData['team2_id']);
        })
        ->where('status', '!=', 'cancelled')
        ->whereBetween('scheduled_at', [
            $scheduledTime->copy()->subMinutes(30),
            $scheduledTime->copy()->addMinutes(30)
        ]);

        if (isset($matchData['exclude_match_id'])) {
            $conflictQuery->where('id', '!=', $matchData['exclude_match_id']);
        }

        $conflictingMatches = $conflictQuery->get();
        
        foreach ($conflictingMatches as $conflictMatch) {
            $conflicts[] = "Team conflict with match #{$conflictMatch->id} at {$conflictMatch->scheduled_at}";
        }

        return $conflicts;
    }

    private function createMatchMaps(MatchModel $match, array $config): void
    {
        $formatConfig = $this->getFormatConfiguration($match->format);
        $mapCount = $formatConfig['max_maps'];
        
        for ($i = 1; $i <= $mapCount; $i++) {
            MatchMap::create([
                'match_id' => $match->id,
                'map_number' => $i,
                'map_name' => $config['maps'][$i-1] ?? null,
                'status' => $i === 1 ? 'upcoming' : 'pending',
                'team1_score' => 0,
                'team2_score' => 0
            ]);
        }
    }

    private function getFormatConfiguration(string $format): array
    {
        $configs = [
            'BO1' => [
                'max_maps' => 1,
                'win_condition' => 1,
                'default_maps' => ['King\'s Row']
            ],
            'BO3' => [
                'max_maps' => 3,
                'win_condition' => 2,
                'default_maps' => ['King\'s Row', 'Hanamura', 'Watchpoint: Gibraltar']
            ],
            'BO5' => [
                'max_maps' => 5,
                'win_condition' => 3,
                'default_maps' => ['King\'s Row', 'Hanamura', 'Watchpoint: Gibraltar', 'Temple of Anubis', 'Dorado']
            ],
            'BO7' => [
                'max_maps' => 7,
                'win_condition' => 4,
                'default_maps' => ['King\'s Row', 'Hanamura', 'Watchpoint: Gibraltar', 'Temple of Anubis', 'Dorado', 'Numbani', 'Volskaya Industries']
            ]
        ];

        return $configs[$format] ?? $configs['BO3'];
    }

    private function processVetoPickAction(MatchModel $match, array $vetoProcess, array $action): array
    {
        $vetoProcess['actions'][] = [
            'team_id' => $action['team_id'],
            'action_type' => $action['type'], // 'veto', 'pick'
            'map' => $action['map'],
            'timestamp' => now()->toISOString()
        ];

        // Remove/add map based on action
        if ($action['type'] === 'veto') {
            $vetoProcess['map_pool'] = array_values(array_diff($vetoProcess['map_pool'], [$action['map']]));
        } elseif ($action['type'] === 'pick') {
            $vetoProcess['final_maps'][] = $action['map'];
            $vetoProcess['map_pool'] = array_values(array_diff($vetoProcess['map_pool'], [$action['map']]));
        }

        // Switch turns
        $vetoProcess['team_turn'] = $action['team_id'] === $match->team1_id ? $match->team2_id : $match->team1_id;

        // Check if process is complete
        $formatConfig = $this->getFormatConfiguration($match->format);
        if (count($vetoProcess['final_maps']) >= $formatConfig['max_maps']) {
            $vetoProcess['completed'] = true;
        }

        return $vetoProcess;
    }

    private function finalizeMatchMaps(MatchModel $match, array $finalMaps): void
    {
        // Delete existing maps
        $match->maps()->delete();
        
        // Create new maps with final selection
        foreach ($finalMaps as $index => $mapName) {
            MatchMap::create([
                'match_id' => $match->id,
                'map_number' => $index + 1,
                'map_name' => $mapName,
                'status' => $index === 0 ? 'upcoming' : 'pending',
                'team1_score' => 0,
                'team2_score' => 0
            ]);
        }
    }

    private function scheduleMatchNotifications(MatchModel $match): void
    {
        // Implementation for scheduling notifications
        // This would integrate with a queue system to send notifications
        // at various intervals before the match
    }

    private function notifyMatchReschedule(MatchModel $match, Carbon $oldTime, Carbon $newTime, string $reason): void
    {
        // Implementation for notifying teams and followers about reschedule
    }

    private function notifyAdminsOfProtest(MatchModel $match, array $protest): void
    {
        // Implementation for notifying administrators about match protests
    }

    private function notifyMatchForfeit(MatchModel $match, int $forfeitingTeamId, string $reason): void
    {
        // Implementation for notifying about forfeits
    }

    private function updateEventStandingsAfterForfeit(MatchModel $match, int $forfeitingTeamId, int $winningTeamId): void
    {
        // Implementation for updating event standings after forfeit
    }

    private function applyProtestResolutionActions(MatchModel $match, array $actions): void
    {
        // Implementation for applying protest resolution actions
        // Could include score changes, replays, etc.
    }

    private function getBasicMatchStats(MatchModel $match): array
    {
        return [
            'duration' => $match->duration,
            'total_maps' => $match->maps()->count(),
            'completed_maps' => $match->maps()->where('status', 'completed')->count(),
            'team1_wins' => $match->maps()->where('winner_id', $match->team1_id)->count(),
            'team2_wins' => $match->maps()->where('winner_id', $match->team2_id)->count()
        ];
    }

    private function getTeamPerformanceStats(MatchModel $match): array
    {
        // Implementation for team performance statistics
        return [];
    }

    private function getPlayerPerformanceStats(MatchModel $match): array
    {
        // Implementation for player performance statistics
        return [];
    }

    private function getMapStatistics(MatchModel $match): array
    {
        // Implementation for map-specific statistics
        return [];
    }

    private function getMatchTimeline(MatchModel $match): array
    {
        // Implementation for match timeline events
        return [];
    }

    private function getComparativeAnalysis(MatchModel $match): array
    {
        // Implementation for comparative analysis between teams
        return [];
    }

    private function updateCurrentMapScore(MatchModel $match, array $mapData): void
    {
        $currentMap = $match->getCurrentLiveMap();
        if ($currentMap) {
            $currentMap->update([
                'team1_score' => $mapData['team1_score'],
                'team2_score' => $mapData['team2_score'],
                'status' => $mapData['status'] ?? $currentMap->status
            ]);
        }
    }

    private function checkMatchCompletion(MatchModel $match): void
    {
        $formatConfig = $this->getFormatConfiguration($match->format);
        
        if ($match->team1_score >= $formatConfig['win_condition'] || 
            $match->team2_score >= $formatConfig['win_condition']) {
            
            $match->update([
                'status' => 'completed',
                'ended_at' => now(),
                'winner_id' => $match->team1_score > $match->team2_score ? $match->team1_id : $match->team2_id
            ]);
        }
    }
}