<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\Tournament;
use App\Services\TournamentIntegrationService;
use App\Events\BracketUpdated;
use App\Events\TournamentPhaseChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GenerateTournamentBracket implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    protected $tournament;
    protected array $config;
    protected string $format;

    public function __construct($tournament, array $config)
    {
        $this->tournament = $tournament;
        $this->config = $config;
        $this->format = $config['format'] ?? 'double_elimination';
        
        // Set queue priority based on tournament tier
        if ($tournament instanceof Event && $tournament->tier === 'S') {
            $this->onQueue('high-priority');
        } else {
            $this->onQueue('tournaments');
        }
    }

    public function handle(TournamentIntegrationService $tournamentService): void
    {
        try {
            Log::info('Starting bracket generation', [
                'tournament_id' => $this->tournament->id,
                'tournament_type' => get_class($this->tournament),
                'format' => $this->format,
                'config' => $this->config
            ]);

            // Cache the generation status
            $cacheKey = "bracket_generation_{$this->tournament->id}";
            Cache::put($cacheKey, [
                'status' => 'processing',
                'started_at' => now(),
                'format' => $this->format
            ], 600);

            // Generate the bracket based on format
            $result = match($this->format) {
                'double_elimination' => $this->generateDoubleElimination($tournamentService),
                'single_elimination' => $this->generateSingleElimination($tournamentService),
                'swiss' => $this->generateSwiss($tournamentService),
                'round_robin' => $this->generateRoundRobin($tournamentService),
                'liquipedia_style' => $this->generateLiquipediaStyle($tournamentService),
                default => throw new \Exception("Unsupported tournament format: {$this->format}")
            };

            // Update tournament status
            $this->tournament->update([
                'status' => 'ongoing',
                'bracket_data' => $result['bracket_data'] ?? null,
                'current_round' => 1,
                'total_rounds' => $result['total_rounds'] ?? 0
            ]);

            // Cache successful completion
            Cache::put($cacheKey, [
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $result,
                'total_matches' => $result['total_matches'] ?? 0
            ], 3600);

            // Broadcast tournament update
            event(new TournamentPhaseChanged(
                $this->tournament,
                [
                    'phase' => 'bracket_generated',
                    'format' => $this->format,
                    'total_matches' => $result['total_matches'] ?? 0,
                    'brackets' => $result['brackets'] ?? []
                ],
                'bracket_generation'
            ));

            // Cache live update for SSE
            $this->cacheSSEUpdate([
                'type' => 'bracket_generated',
                'data' => [
                    'tournament_id' => $this->tournament->id,
                    'format' => $this->format,
                    'status' => 'completed',
                    'brackets' => $result['brackets'] ?? [],
                    'liquipedia_notation' => $result['liquipedia_notation'] ?? []
                ]
            ]);

            Log::info('Bracket generation completed successfully', [
                'tournament_id' => $this->tournament->id,
                'format' => $this->format,
                'total_matches' => $result['total_matches'] ?? 0,
                'execution_time' => microtime(true) - LARAVEL_START
            ]);

        } catch (\Exception $e) {
            Log::error('Bracket generation failed', [
                'tournament_id' => $this->tournament->id,
                'format' => $this->format,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Cache failure status
            Cache::put($cacheKey, [
                'status' => 'failed',
                'failed_at' => now(),
                'error' => $e->getMessage()
            ], 3600);

            // Re-throw for retry mechanism
            throw $e;
        }
    }

    protected function generateDoubleElimination(TournamentIntegrationService $service): array
    {
        $teams = $this->tournament->teams;
        $result = $service->bracketService->generateDoubleEliminationBracket(
            $this->tournament,
            $teams,
            $this->config
        );

        return [
            'bracket_data' => $result,
            'total_matches' => collect($result['upper_matches'])->count() + 
                             collect($result['lower_matches'])->count() + 
                             collect($result['final_matches'])->count(),
            'total_rounds' => $this->calculateDoubleEliminationRounds(count($teams)),
            'brackets' => [
                'upper_bracket' => $result['upper_stage'],
                'lower_bracket' => $result['lower_stage'],
                'grand_final' => $result['final_stage']
            ],
            'liquipedia_notation' => $service->generateLiquipediaNotation($result)
        ];
    }

    protected function generateSingleElimination(TournamentIntegrationService $service): array
    {
        $teams = $this->tournament->teams;
        $result = $service->bracketService->generateSingleEliminationBracket(
            $this->tournament,
            $teams,
            $this->config
        );

        return [
            'bracket_data' => $result,
            'total_matches' => collect($result['matches'])->count(),
            'total_rounds' => ceil(log(count($teams), 2)),
            'brackets' => [
                'main_bracket' => $result['main_stage']
            ],
            'liquipedia_notation' => $service->generateLiquipediaNotation($result)
        ];
    }

    protected function generateSwiss(TournamentIntegrationService $service): array
    {
        $teams = $this->tournament->teams;
        $result = $service->bracketService->generateSwissBracket(
            $this->tournament,
            $teams,
            $this->config
        );

        return [
            'bracket_data' => $result,
            'total_matches' => count($teams) * ($this->config['rounds'] ?? ceil(log(count($teams), 2))) / 2,
            'total_rounds' => $this->config['rounds'] ?? ceil(log(count($teams), 2)),
            'brackets' => [
                'swiss_stage' => $result['swiss_stage']
            ],
            'liquipedia_notation' => []
        ];
    }

    protected function generateRoundRobin(TournamentIntegrationService $service): array
    {
        $teams = $this->tournament->teams;
        $result = $service->bracketService->generateRoundRobinBracket(
            $this->tournament,
            $teams,
            $this->config
        );

        $teamCount = count($teams);
        $totalMatches = ($teamCount * ($teamCount - 1)) / 2;
        if ($this->config['double_round_robin'] ?? false) {
            $totalMatches *= 2;
        }

        return [
            'bracket_data' => $result,
            'total_matches' => $totalMatches,
            'total_rounds' => $teamCount - 1,
            'brackets' => [
                'round_robin_stage' => $result['round_robin_stage']
            ],
            'liquipedia_notation' => []
        ];
    }

    protected function generateLiquipediaStyle(TournamentIntegrationService $service): array
    {
        // Full Liquipedia-style tournament with multiple phases
        $result = $service->createLiquipediaTournament($this->tournament, $this->config);

        return [
            'bracket_data' => $result,
            'total_matches' => $this->calculateLiquipediaMatches($result),
            'total_rounds' => $this->calculateLiquipediaRounds($result),
            'brackets' => $result['brackets'],
            'liquipedia_notation' => $result['liquipedia_format'],
            'phases' => $result['phases']
        ];
    }

    protected function calculateDoubleEliminationRounds(int $teamCount): int
    {
        return ceil(log($teamCount, 2)) * 2 - 1;
    }

    protected function calculateLiquipediaMatches(array $result): int
    {
        $total = 0;
        foreach ($result['brackets'] as $bracket) {
            $total += collect($bracket['matches'] ?? [])->count();
        }
        return $total;
    }

    protected function calculateLiquipediaRounds(array $result): int
    {
        $maxRounds = 0;
        foreach ($result['brackets'] as $bracket) {
            $rounds = collect($bracket['matches'] ?? [])->max('round_number') ?? 0;
            $maxRounds = max($maxRounds, $rounds);
        }
        return $maxRounds;
    }

    protected function cacheSSEUpdate(array $update): void
    {
        $cacheKey = "live_update_event_{$this->tournament->id}_bracket_generated";
        Cache::put($cacheKey, $update, 300); // 5 minutes
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bracket generation job failed permanently', [
            'tournament_id' => $this->tournament->id,
            'format' => $this->format,
            'attempts' => $this->attempts,
            'exception' => $exception->getMessage()
        ]);

        // Update tournament status to indicate failure
        $this->tournament->update([
            'status' => 'cancelled' // or another appropriate status
        ]);

        // Cache failure for admin interface
        $cacheKey = "bracket_generation_{$this->tournament->id}";
        Cache::put($cacheKey, [
            'status' => 'failed_permanently',
            'failed_at' => now(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts
        ], 86400); // 24 hours
    }
}