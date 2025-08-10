<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TournamentService
{
    protected $broadcastService;
    protected $swissService;
    protected $bracketService;

    public function __construct(
        TournamentBroadcastService $broadcastService,
        SwissSystemService $swissService,
        BracketGenerationService $bracketService
    ) {
        $this->broadcastService = $broadcastService;
        $this->swissService = $swissService;
        $this->bracketService = $bracketService;
    }

    /**
     * Create a new tournament
     */
    public function createTournament(array $data, User $organizer): Tournament
    {
        try {
            DB::beginTransaction();

            $tournament = Tournament::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'format' => $data['format'],
                'max_teams' => $data['max_teams'],
                'entry_fee' => $data['entry_fee'] ?? 0,
                'prize_pool' => $data['prize_pool'] ?? 0,
                'registration_start' => $data['registration_start'] ?? now(),
                'registration_end' => $data['registration_end'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'region' => $data['region'] ?? 'global',
                'rules' => $data['rules'] ?? null,
                'prize_distribution' => $data['prize_distribution'] ?? [],
                'match_settings' => $data['match_settings'] ?? [],
                'qualification_settings' => $data['qualification_settings'] ?? [],
                'streaming_settings' => $data['streaming_settings'] ?? [],
                'organizer_id' => $organizer->id,
                'status' => 'draft',
                'registration_open' => false,
                'current_phase' => 'registration',
            ]);

            // Create tournament phases
            $this->createTournamentPhases($tournament, $data['phases'] ?? []);

            // Set up initial tournament settings
            $this->setupTournamentSettings($tournament, $data);

            DB::commit();

            // Broadcast tournament creation
            $this->broadcastService->broadcastTournamentUpdate(
                $tournament,
                'tournament_created',
                [],
                ['organizer' => $organizer->name]
            );

            Log::info('Tournament created successfully', [
                'tournament_id' => $tournament->id,
                'organizer_id' => $organizer->id
            ]);

            return $tournament;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start tournament registration
     */
    public function startRegistration(Tournament $tournament): bool
    {
        try {
            if ($tournament->status !== 'draft') {
                throw new \Exception('Tournament must be in draft status to start registration');
            }

            DB::beginTransaction();

            $tournament->update([
                'status' => 'registration_open',
                'registration_open' => true,
                'current_phase' => 'registration'
            ]);

            // Start registration phase
            $registrationPhase = $tournament->phases()->where('type', 'registration')->first();
            if ($registrationPhase) {
                $registrationPhase->update([
                    'status' => 'active',
                    'started_at' => now()
                ]);
            }

            DB::commit();

            // Broadcast registration opening
            $this->broadcastService->broadcastTournamentUpdate(
                $tournament,
                'registration_opened',
                ['registration_open' => true],
                ['registration_deadline' => $tournament->registration_end?->toISOString()]
            );

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start tournament registration: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Register team for tournament
     */
    public function registerTeam(Tournament $tournament, Team $team, User $user, array $playerData = []): ?TournamentRegistration
    {
        try {
            // Validate registration eligibility
            if (!$this->canTeamRegister($tournament, $team)) {
                return null;
            }

            DB::beginTransaction();

            $registration = TournamentRegistration::create([
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'registered_by' => $user->id,
                'status' => $tournament->requires_approval ? 'pending' : 'approved',
                'player_data' => $playerData,
                'registered_at' => now(),
            ]);

            // Update tournament team count
            $tournament->increment('current_team_count');

            // Auto-approve if no approval required
            if (!$tournament->requires_approval) {
                $this->approveTeamRegistration($registration);
            }

            DB::commit();

            // Broadcast team registration
            $this->broadcastService->broadcastTeamRegistered($tournament, $team, $registration);

            return $registration;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Team registration failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Start tournament
     */
    public function startTournament(Tournament $tournament): bool
    {
        try {
            if (!$this->canStartTournament($tournament)) {
                return false;
            }

            DB::beginTransaction();

            // Close registration
            $tournament->update([
                'registration_open' => false,
                'status' => 'ongoing',
                'started_at' => now()
            ]);

            // Complete registration phase
            $registrationPhase = $tournament->phases()->where('type', 'registration')->first();
            if ($registrationPhase) {
                $registrationPhase->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }

            // Generate bracket and start first phase
            $this->generateInitialBracket($tournament);

            // Start check-in phase or first competitive phase
            $this->startNextPhase($tournament);

            DB::commit();

            // Broadcast tournament start
            $this->broadcastService->broadcastTournamentStarted($tournament);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament start failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Complete tournament
     */
    public function completeTournament(Tournament $tournament, array $results = []): bool
    {
        try {
            DB::beginTransaction();

            // Calculate final results if not provided
            if (empty($results)) {
                $results = $this->calculateFinalResults($tournament);
            }

            // Update tournament status
            $tournament->update([
                'status' => 'completed',
                'completed_at' => now(),
                'final_results' => $results
            ]);

            // Complete all active phases
            $tournament->phases()
                ->whereIn('status', ['active', 'pending'])
                ->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);

            // Update team placements
            $this->updateTeamPlacements($tournament, $results);

            // Distribute prizes if applicable
            if ($tournament->prize_pool > 0) {
                $this->distributePrizes($tournament, $results);
            }

            DB::commit();

            // Broadcast tournament completion
            $this->broadcastService->broadcastTournamentCompleted($tournament, $results);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Tournament completion failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tournament statistics
     */
    public function getTournamentStatistics(Tournament $tournament): array
    {
        return Cache::remember("tournament_stats_{$tournament->id}", 300, function () use ($tournament) {
            $stats = [
                'basic_info' => [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'type' => $tournament->type,
                    'format' => $tournament->format,
                    'status' => $tournament->status,
                    'current_phase' => $tournament->current_phase,
                ],
                'participation' => [
                    'total_teams' => $tournament->current_team_count,
                    'max_teams' => $tournament->max_teams,
                    'fill_percentage' => round(($tournament->current_team_count / $tournament->max_teams) * 100, 1),
                    'total_players' => $this->getTotalPlayersCount($tournament),
                ],
                'timeline' => [
                    'registration_start' => $tournament->registration_start?->toISOString(),
                    'registration_end' => $tournament->registration_end?->toISOString(),
                    'start_date' => $tournament->start_date?->toISOString(),
                    'end_date' => $tournament->end_date?->toISOString(),
                    'duration_hours' => $tournament->started_at && $tournament->completed_at
                        ? $tournament->started_at->diffInHours($tournament->completed_at)
                        : null,
                ],
                'matches' => $this->getMatchStatistics($tournament),
                'prizes' => [
                    'prize_pool' => $tournament->prize_pool,
                    'entry_fee' => $tournament->entry_fee,
                    'total_collected' => $tournament->entry_fee * $tournament->current_team_count,
                ],
            ];

            // Add format-specific statistics
            if ($tournament->format === 'swiss') {
                $stats['swiss'] = $this->swissService->getSwissStatistics($tournament);
            }

            return $stats;
        });
    }

    /**
     * Get tournament bracket data
     */
    public function getBracketData(Tournament $tournament): array
    {
        return Cache::remember("tournament_bracket_{$tournament->id}", 300, function () use ($tournament) {
            return match($tournament->format) {
                'swiss' => $this->getSwissBracketData($tournament),
                'single_elimination' => $this->getSingleEliminationBracketData($tournament),
                'double_elimination' => $this->getDoubleEliminationBracketData($tournament),
                'round_robin' => $this->getRoundRobinBracketData($tournament),
                'group_stage_playoffs' => $this->getGroupStageBracketData($tournament),
                default => []
            };
        });
    }

    /**
     * Handle match completion
     */
    public function handleMatchCompletion(BracketMatch $match): void
    {
        try {
            $tournament = $match->tournament;

            // Update tournament statistics
            $this->updateTournamentProgress($tournament);

            // Handle format-specific completion logic
            match($tournament->format) {
                'swiss' => $this->swissService->handleSwissMatchCompletion($match),
                'single_elimination' => $this->handleEliminationMatchCompletion($match),
                'double_elimination' => $this->handleDoubleEliminationMatchCompletion($match),
                default => null
            };

            // Check if tournament is complete
            if ($this->isTournamentComplete($tournament)) {
                $this->completeTournament($tournament);
            }

            // Broadcast match completion
            $this->broadcastService->broadcastMatchCompleted($match);

        } catch (\Exception $e) {
            Log::error('Match completion handling failed: ' . $e->getMessage());
        }
    }

    // Private helper methods

    private function createTournamentPhases(Tournament $tournament, array $phasesData): void
    {
        $defaultPhases = $this->getDefaultPhases($tournament->format);
        $phases = !empty($phasesData) ? $phasesData : $defaultPhases;

        foreach ($phases as $index => $phaseData) {
            TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name' => $phaseData['name'],
                'type' => $phaseData['type'],
                'order' => $index + 1,
                'status' => $index === 0 ? 'pending' : 'not_started',
                'settings' => $phaseData['settings'] ?? [],
            ]);
        }
    }

    private function getDefaultPhases(string $format): array
    {
        return match($format) {
            'swiss' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Swiss Rounds', 'type' => 'swiss'],
                ['name' => 'Playoffs', 'type' => 'playoffs'],
            ],
            'single_elimination' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Playoffs', 'type' => 'playoffs'],
            ],
            'double_elimination' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Upper Bracket', 'type' => 'upper_bracket'],
                ['name' => 'Lower Bracket', 'type' => 'lower_bracket'],
                ['name' => 'Grand Final', 'type' => 'grand_final'],
            ],
            'round_robin' => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Round Robin', 'type' => 'round_robin'],
            ],
            default => [
                ['name' => 'Registration', 'type' => 'registration'],
                ['name' => 'Check-in', 'type' => 'check_in'],
                ['name' => 'Competition', 'type' => 'competition'],
            ]
        };
    }

    private function setupTournamentSettings(Tournament $tournament, array $data): void
    {
        // Set up default qualification settings based on format
        if (empty($tournament->qualification_settings)) {
            $tournament->qualification_settings = $this->getDefaultQualificationSettings($tournament->format);
            $tournament->save();
        }

        // Set up default match settings
        if (empty($tournament->match_settings)) {
            $tournament->match_settings = [
                'format' => $data['match_format'] ?? 'bo3',
                'map_pool' => $data['map_pool'] ?? [],
                'veto_format' => $data['veto_format'] ?? 'ban_ban_pick_pick_decide',
            ];
            $tournament->save();
        }
    }

    private function getDefaultQualificationSettings(string $format): array
    {
        return match($format) {
            'swiss' => [
                'swiss_rounds' => 5,
                'swiss_wins_required' => 3,
                'swiss_losses_eliminated' => 3,
                'qualification_percentage' => 50,
            ],
            'group_stage_playoffs' => [
                'group_size' => 4,
                'teams_advance_per_group' => 2,
                'group_format' => 'round_robin',
            ],
            default => []
        };
    }

    private function canTeamRegister(Tournament $tournament, Team $team): bool
    {
        // Check if registration is open
        if (!$tournament->registration_open || $tournament->status !== 'registration_open') {
            return false;
        }

        // Check if tournament is full
        if ($tournament->current_team_count >= $tournament->max_teams) {
            return false;
        }

        // Check if team is already registered
        if ($tournament->registrations()->where('team_id', $team->id)->exists()) {
            return false;
        }

        // Check region restrictions if applicable
        if ($tournament->region !== 'global' && $team->region !== $tournament->region) {
            return false;
        }

        return true;
    }

    private function approveTeamRegistration(TournamentRegistration $registration): void
    {
        $registration->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);
    }

    private function canStartTournament(Tournament $tournament): bool
    {
        // Must have minimum number of teams
        $minTeams = $this->getMinimumTeamsForFormat($tournament->format);
        if ($tournament->current_team_count < $minTeams) {
            return false;
        }

        // Must be in correct status
        if (!in_array($tournament->status, ['registration_open', 'registration_closed'])) {
            return false;
        }

        return true;
    }

    private function getMinimumTeamsForFormat(string $format): int
    {
        return match($format) {
            'round_robin' => 3,
            'swiss' => 4,
            default => 2
        };
    }

    private function generateInitialBracket(Tournament $tournament): void
    {
        // Generate seeding
        $this->generateSeeding($tournament);

        // Generate bracket based on format
        $this->bracketService->generateTournamentBrackets($tournament);
    }

    private function generateSeeding(Tournament $tournament): void
    {
        $teams = $tournament->teams()
            ->wherePivot('status', 'approved')
            ->get();

        // Simple random seeding for now
        $seededTeams = $teams->shuffle();

        foreach ($seededTeams as $index => $team) {
            $tournament->teams()->updateExistingPivot($team->id, [
                'seed_number' => $index + 1
            ]);
        }
    }

    private function startNextPhase(Tournament $tournament): void
    {
        $nextPhase = $tournament->phases()
            ->where('status', 'pending')
            ->orderBy('order')
            ->first();

        if ($nextPhase) {
            $nextPhase->update([
                'status' => 'active',
                'started_at' => now()
            ]);

            $tournament->update([
                'current_phase' => $nextPhase->name
            ]);

            // Broadcast phase start
            $this->broadcastService->broadcastPhaseStarted($tournament, $nextPhase);
        }
    }

    private function calculateFinalResults(Tournament $tournament): array
    {
        // Implementation depends on tournament format
        return match($tournament->format) {
            'swiss' => $this->calculateSwissFinalResults($tournament),
            'single_elimination' => $this->calculateEliminationResults($tournament),
            default => []
        };
    }

    private function calculateSwissFinalResults(Tournament $tournament): array
    {
        return $tournament->teams()
            ->orderByDesc('pivot_swiss_score')
            ->orderByDesc('pivot_swiss_buchholz')
            ->get()
            ->map(function ($team, $index) {
                return [
                    'placement' => $index + 1,
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'score' => $team->pivot->swiss_score ?? 0,
                    'wins' => $team->pivot->swiss_wins ?? 0,
                    'losses' => $team->pivot->swiss_losses ?? 0,
                ];
            })
            ->toArray();
    }

    private function calculateEliminationResults(Tournament $tournament): array
    {
        // Calculate based on bracket elimination order
        return [];
    }

    private function updateTeamPlacements(Tournament $tournament, array $results): void
    {
        foreach ($results as $result) {
            $tournament->teams()->updateExistingPivot($result['team_id'], [
                'final_placement' => $result['placement']
            ]);
        }
    }

    private function distributePrizes(Tournament $tournament, array $results): void
    {
        $prizeDistribution = $tournament->prize_distribution ?? [];
        
        foreach ($results as $result) {
            $placement = $result['placement'];
            $prizeKey = $placement <= 3 ? "{$placement}st" : "top_{$placement}";
            
            if (isset($prizeDistribution[$prizeKey])) {
                $prizeAmount = ($tournament->prize_pool * $prizeDistribution[$prizeKey]) / 100;
                
                // Record prize distribution (would integrate with payment system)
                Log::info("Prize distributed", [
                    'tournament_id' => $tournament->id,
                    'team_id' => $result['team_id'],
                    'placement' => $placement,
                    'prize_amount' => $prizeAmount
                ]);
            }
        }
    }

    private function getTotalPlayersCount(Tournament $tournament): int
    {
        return $tournament->teams()
            ->withCount('players')
            ->get()
            ->sum('players_count');
    }

    private function getMatchStatistics(Tournament $tournament): array
    {
        $matches = BracketMatch::where('tournament_id', $tournament->id);

        return [
            'total_matches' => $matches->count(),
            'completed_matches' => $matches->clone()->where('status', 'completed')->count(),
            'pending_matches' => $matches->clone()->where('status', 'pending')->count(),
            'ongoing_matches' => $matches->clone()->where('status', 'ongoing')->count(),
            'cancelled_matches' => $matches->clone()->where('status', 'cancelled')->count(),
        ];
    }

    private function getSwissBracketData(Tournament $tournament): array
    {
        return [
            'type' => 'swiss',
            'standings' => $this->swissService->calculateSwissStandings($tournament, null, true, false),
            'current_round' => $this->getCurrentRound($tournament),
            'total_rounds' => $this->getTotalRounds($tournament),
        ];
    }

    private function getSingleEliminationBracketData(Tournament $tournament): array
    {
        return ['type' => 'single_elimination']; // Simplified for now
    }

    private function getDoubleEliminationBracketData(Tournament $tournament): array
    {
        return ['type' => 'double_elimination']; // Simplified for now
    }

    private function getRoundRobinBracketData(Tournament $tournament): array
    {
        return ['type' => 'round_robin']; // Simplified for now
    }

    private function getGroupStageBracketData(Tournament $tournament): array
    {
        return ['type' => 'group_stage_playoffs']; // Simplified for now
    }

    private function getCurrentRound(Tournament $tournament): int
    {
        return BracketMatch::where('tournament_id', $tournament->id)->max('round') ?? 0;
    }

    private function getTotalRounds(Tournament $tournament): int
    {
        $settings = $tournament->qualification_settings ?? [];
        return match($tournament->format) {
            'swiss' => $settings['swiss_rounds'] ?? ceil(log($tournament->current_team_count, 2)),
            'single_elimination' => ceil(log($tournament->current_team_count, 2)),
            default => 1
        };
    }

    private function updateTournamentProgress(Tournament $tournament): void
    {
        // Clear tournament statistics cache
        Cache::forget("tournament_stats_{$tournament->id}");
        Cache::forget("tournament_bracket_{$tournament->id}");
    }

    private function handleEliminationMatchCompletion(BracketMatch $match): void
    {
        // Handle single elimination logic
    }

    private function handleDoubleEliminationMatchCompletion(BracketMatch $match): void
    {
        // Handle double elimination logic
    }

    private function isTournamentComplete(Tournament $tournament): bool
    {
        // Check if all matches are complete and tournament should end
        return match($tournament->format) {
            'swiss' => $this->isSwissTournamentComplete($tournament),
            'single_elimination' => $this->isEliminationTournamentComplete($tournament),
            default => false
        };
    }

    private function isSwissTournamentComplete(Tournament $tournament): bool
    {
        // Swiss is complete when Swiss phase is done and playoffs (if any) are complete
        return false; // Simplified for now
    }

    private function isEliminationTournamentComplete(Tournament $tournament): bool
    {
        // Elimination is complete when final match is played
        return false; // Simplified for now
    }
}