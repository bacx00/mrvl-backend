<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentPhase;
use App\Models\TournamentRegistration;
use App\Models\BracketMatch;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class EnhancedTournamentCacheService extends TournamentCacheService
{
    // Enhanced cache TTL configurations
    const ENHANCED_CACHE_TTL = [
        'tournament_list' => 600,         // 10 minutes
        'tournament_details' => 300,      // 5 minutes
        'tournament_statistics' => 180,   // 3 minutes
        'tournament_phases' => 300,       // 5 minutes
        'tournament_registrations' => 120, // 2 minutes
        'tournament_bracket_data' => 300, // 5 minutes
        'user_tournaments' => 300,        // 5 minutes
        'tournament_search' => 600,       // 10 minutes
        'tournament_analytics' => 900,    // 15 minutes
        'admin_dashboard' => 300,         // 5 minutes
    ];

    // Enhanced cache key prefixes
    const ENHANCED_CACHE_KEYS = [
        'tournament_list' => 'tournaments:list:',
        'tournament_details' => 'tournament:details:',
        'tournament_stats' => 'tournament:stats:',
        'tournament_phases' => 'tournament:phases:',
        'tournament_registrations' => 'tournament:registrations:',
        'tournament_bracket' => 'tournament:bracket:',
        'user_tournaments' => 'user:tournaments:',
        'tournament_search' => 'tournaments:search:',
        'tournament_analytics' => 'tournament:analytics:',
        'admin_dashboard' => 'admin:tournaments:dashboard',
    ];

    /**
     * Cache tournament list with pagination and filters
     */
    public function cacheTournamentList(array $filters, Collection $tournaments, int $total): void
    {
        $filterKey = md5(serialize($filters));
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_list'] . $filterKey;
        
        $listData = [
            'tournaments' => $tournaments->map(function ($tournament) {
                return $this->getTournamentSummary($tournament);
            })->toArray(),
            'total' => $total,
            'filters' => $filters,
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $listData, self::ENHANCED_CACHE_TTL['tournament_list']);
    }

    /**
     * Get cached tournament list
     */
    public function getCachedTournamentList(array $filters): ?array
    {
        $filterKey = md5(serialize($filters));
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_list'] . $filterKey;
        
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament details with comprehensive data
     */
    public function cacheTournamentDetails(Tournament $tournament): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_details'] . $tournament->id;
        
        $details = [
            'tournament' => [
                'id' => $tournament->id,
                'name' => $tournament->name,
                'description' => $tournament->description,
                'type' => $tournament->type,
                'format' => $tournament->format,
                'status' => $tournament->status,
                'current_phase' => $tournament->current_phase,
                'max_teams' => $tournament->max_teams,
                'current_team_count' => $tournament->current_team_count,
                'entry_fee' => $tournament->entry_fee,
                'prize_pool' => $tournament->prize_pool,
                'prize_distribution' => $tournament->prize_distribution,
                'region' => $tournament->region,
                'timezone' => $tournament->timezone,
                'registration_start' => $tournament->registration_start?->toISOString(),
                'registration_end' => $tournament->registration_end?->toISOString(),
                'start_date' => $tournament->start_date?->toISOString(),
                'end_date' => $tournament->end_date?->toISOString(),
                'registration_open' => $tournament->registration_open,
                'check_in_open' => $tournament->check_in_open,
                'rules' => $tournament->rules,
                'match_settings' => $tournament->match_settings,
                'streaming_settings' => $tournament->streaming_settings,
            ],
            'organizer' => $tournament->organizer ? [
                'id' => $tournament->organizer->id,
                'name' => $tournament->organizer->name,
            ] : null,
            'phases' => $this->getCachedTournamentPhases($tournament->id),
            'registration_stats' => $this->getRegistrationStats($tournament),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $details, self::ENHANCED_CACHE_TTL['tournament_details']);
    }

    /**
     * Get cached tournament details
     */
    public function getCachedTournamentDetails(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_details'] . $tournamentId;
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament statistics
     */
    public function cacheTournamentStatistics(Tournament $tournament, array $statistics): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_stats'] . $tournament->id;
        
        $statsData = [
            'statistics' => $statistics,
            'calculated_at' => now()->toISOString(),
            'tournament_status' => $tournament->status,
        ];

        Cache::put($cacheKey, $statsData, self::ENHANCED_CACHE_TTL['tournament_statistics']);
    }

    /**
     * Get cached tournament statistics
     */
    public function getCachedTournamentStatistics(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_stats'] . $tournamentId;
        $cached = Cache::get($cacheKey);
        
        return $cached ? $cached['statistics'] : null;
    }

    /**
     * Cache tournament phases
     */
    public function cacheTournamentPhases(int $tournamentId, Collection $phases): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_phases'] . $tournamentId;
        
        $phasesData = $phases->map(function ($phase) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'type' => $phase->type,
                'status' => $phase->status,
                'order' => $phase->order,
                'settings' => $phase->settings,
                'started_at' => $phase->started_at?->toISOString(),
                'completed_at' => $phase->completed_at?->toISOString(),
            ];
        })->toArray();

        Cache::put($cacheKey, $phasesData, self::ENHANCED_CACHE_TTL['tournament_phases']);
    }

    /**
     * Get cached tournament phases
     */
    public function getCachedTournamentPhases(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_phases'] . $tournamentId;
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament registrations data
     */
    public function cacheTournamentRegistrations(Tournament $tournament, Collection $registrations): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_registrations'] . $tournament->id;
        
        $registrationData = [
            'registrations' => $registrations->map(function ($registration) {
                return [
                    'id' => $registration->id,
                    'team_id' => $registration->team_id,
                    'team_name' => $registration->team?->name,
                    'team_short_name' => $registration->team?->short_name,
                    'team_logo' => $registration->team?->logo,
                    'status' => $registration->status,
                    'check_in_status' => $registration->check_in_status,
                    'registered_at' => $registration->created_at->toISOString(),
                    'checked_in_at' => $registration->checked_in_at?->toISOString(),
                ];
            })->toArray(),
            'stats' => $this->getRegistrationStats($tournament),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $registrationData, self::ENHANCED_CACHE_TTL['tournament_registrations']);
    }

    /**
     * Get cached tournament registrations
     */
    public function getCachedTournamentRegistrations(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_registrations'] . $tournamentId;
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament bracket data
     */
    public function cacheTournamentBracketData(Tournament $tournament, array $bracketData): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_bracket'] . $tournament->id;
        
        $data = [
            'bracket' => $bracketData,
            'tournament_format' => $tournament->format,
            'tournament_status' => $tournament->status,
            'current_phase' => $tournament->current_phase,
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $data, self::ENHANCED_CACHE_TTL['tournament_bracket_data']);
    }

    /**
     * Get cached tournament bracket data
     */
    public function getCachedTournamentBracketData(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_bracket'] . $tournamentId;
        $cached = Cache::get($cacheKey);
        
        return $cached ? $cached['bracket'] : null;
    }

    /**
     * Cache user's tournaments (following, participating, etc.)
     */
    public function cacheUserTournaments(int $userId, array $tournaments): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['user_tournaments'] . $userId;
        
        Cache::put($cacheKey, $tournaments, self::ENHANCED_CACHE_TTL['user_tournaments']);
    }

    /**
     * Get cached user tournaments
     */
    public function getCachedUserTournaments(int $userId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['user_tournaments'] . $userId;
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament search results
     */
    public function cacheTournamentSearch(string $query, array $filters, Collection $results): void
    {
        $searchKey = md5($query . serialize($filters));
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_search'] . $searchKey;
        
        $searchData = [
            'query' => $query,
            'filters' => $filters,
            'results' => $results->map(function ($tournament) {
                return $this->getTournamentSummary($tournament);
            })->toArray(),
            'count' => $results->count(),
            'searched_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $searchData, self::ENHANCED_CACHE_TTL['tournament_search']);
    }

    /**
     * Get cached tournament search results
     */
    public function getCachedTournamentSearch(string $query, array $filters): ?array
    {
        $searchKey = md5($query . serialize($filters));
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_search'] . $searchKey;
        
        return Cache::get($cacheKey);
    }

    /**
     * Cache tournament analytics data
     */
    public function cacheTournamentAnalytics(int $tournamentId, array $analytics): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_analytics'] . $tournamentId;
        
        $analyticsData = [
            'analytics' => $analytics,
            'generated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $analyticsData, self::ENHANCED_CACHE_TTL['tournament_analytics']);
    }

    /**
     * Get cached tournament analytics
     */
    public function getCachedTournamentAnalytics(int $tournamentId): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['tournament_analytics'] . $tournamentId;
        $cached = Cache::get($cacheKey);
        
        return $cached ? $cached['analytics'] : null;
    }

    /**
     * Cache admin dashboard data
     */
    public function cacheAdminDashboard(array $dashboardData): void
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['admin_dashboard'];
        
        $data = [
            'dashboard' => $dashboardData,
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $data, self::ENHANCED_CACHE_TTL['admin_dashboard']);
    }

    /**
     * Get cached admin dashboard data
     */
    public function getCachedAdminDashboard(): ?array
    {
        $cacheKey = self::ENHANCED_CACHE_KEYS['admin_dashboard'];
        $cached = Cache::get($cacheKey);
        
        return $cached ? $cached['dashboard'] : null;
    }

    /**
     * Invalidate all tournament-related caches
     */
    public function invalidateAllTournamentCaches(Tournament $tournament): void
    {
        $keysToInvalidate = [
            // Tournament-specific caches
            self::ENHANCED_CACHE_KEYS['tournament_details'] . $tournament->id,
            self::ENHANCED_CACHE_KEYS['tournament_stats'] . $tournament->id,
            self::ENHANCED_CACHE_KEYS['tournament_phases'] . $tournament->id,
            self::ENHANCED_CACHE_KEYS['tournament_registrations'] . $tournament->id,
            self::ENHANCED_CACHE_KEYS['tournament_bracket'] . $tournament->id,
            self::ENHANCED_CACHE_KEYS['tournament_analytics'] . $tournament->id,
            
            // List caches (pattern-based, would need Redis)
            self::ENHANCED_CACHE_KEYS['tournament_list'] . '*',
            self::ENHANCED_CACHE_KEYS['tournament_search'] . '*',
            
            // Admin dashboard
            self::ENHANCED_CACHE_KEYS['admin_dashboard'],
        ];

        // Invalidate specific keys
        foreach ($keysToInvalidate as $key) {
            if (strpos($key, '*') === false) {
                Cache::forget($key);
            }
        }

        // Invalidate user tournament caches for participants
        $this->invalidateUserTournamentCaches($tournament);

        // Call parent invalidation method
        parent::invalidateTournamentCaches($tournament);
    }

    /**
     * Invalidate tournament list caches
     */
    public function invalidateTournamentListCaches(): void
    {
        // This would need Redis KEYS command for pattern matching
        // For now, we'll use cache tags if available
        Cache::tags(['tournament_list', 'tournament_search'])->flush();
        
        // Also invalidate admin dashboard
        Cache::forget(self::ENHANCED_CACHE_KEYS['admin_dashboard']);
    }

    /**
     * Invalidate user tournament caches for tournament participants
     */
    public function invalidateUserTournamentCaches(Tournament $tournament): void
    {
        // Get all users associated with this tournament
        $userIds = collect();
        
        // Tournament followers
        $followers = $tournament->followers()->pluck('users.id');
        $userIds = $userIds->merge($followers);
        
        // Team members
        $teamMembers = $tournament->teams()
            ->with('players')
            ->get()
            ->flatMap(function ($team) {
                return $team->players->pluck('id');
            });
        $userIds = $userIds->merge($teamMembers);
        
        // Invalidate user tournament caches
        foreach ($userIds->unique() as $userId) {
            Cache::forget(self::ENHANCED_CACHE_KEYS['user_tournaments'] . $userId);
        }
    }

    /**
     * Warm up tournament caches
     */
    public function warmTournamentCaches(Tournament $tournament): void
    {
        try {
            // Warm basic tournament details
            $this->cacheTournamentDetails($tournament);
            
            // Warm phases
            $phases = $tournament->phases;
            $this->cacheTournamentPhases($tournament->id, $phases);
            
            // Warm registrations if tournament is accepting registrations
            if ($tournament->registration_open) {
                $registrations = $tournament->registrations()->with('team')->get();
                $this->cacheTournamentRegistrations($tournament, $registrations);
            }
            
            // Warm team data
            parent::warmTournamentCache($tournament);
            
            Log::info("Tournament caches warmed", ['tournament_id' => $tournament->id]);
            
        } catch (\Exception $e) {
            Log::error("Failed to warm tournament caches: " . $e->getMessage(), [
                'tournament_id' => $tournament->id
            ]);
        }
    }

    /**
     * Get cache health statistics
     */
    public function getCacheHealthStats(): array
    {
        $stats = parent::getCacheStatistics();
        
        // Add enhanced cache statistics
        $enhancedStats = [
            'tournament_details' => $this->getCachedItemCount(self::ENHANCED_CACHE_KEYS['tournament_details'] . '*'),
            'tournament_lists' => $this->getCachedItemCount(self::ENHANCED_CACHE_KEYS['tournament_list'] . '*'),
            'search_results' => $this->getCachedItemCount(self::ENHANCED_CACHE_KEYS['tournament_search'] . '*'),
            'user_tournaments' => $this->getCachedItemCount(self::ENHANCED_CACHE_KEYS['user_tournaments'] . '*'),
            'analytics_data' => $this->getCachedItemCount(self::ENHANCED_CACHE_KEYS['tournament_analytics'] . '*'),
        ];

        return array_merge($stats, ['enhanced' => $enhancedStats]);
    }

    // Helper methods

    private function getTournamentSummary(Tournament $tournament): array
    {
        return [
            'id' => $tournament->id,
            'name' => $tournament->name,
            'type' => $tournament->type,
            'format' => $tournament->format,
            'status' => $tournament->status,
            'current_phase' => $tournament->current_phase,
            'teams_registered' => $tournament->current_team_count,
            'max_teams' => $tournament->max_teams,
            'prize_pool' => $tournament->prize_pool,
            'entry_fee' => $tournament->entry_fee,
            'region' => $tournament->region,
            'registration_start' => $tournament->registration_start?->toISOString(),
            'registration_end' => $tournament->registration_end?->toISOString(),
            'start_date' => $tournament->start_date?->toISOString(),
            'registration_open' => $tournament->registration_open,
            'organizer_name' => $tournament->organizer?->name,
        ];
    }

    private function getRegistrationStats(Tournament $tournament): array
    {
        $registrations = $tournament->registrations();
        
        return [
            'total' => $registrations->count(),
            'pending' => $registrations->clone()->where('status', 'pending')->count(),
            'approved' => $registrations->clone()->where('status', 'approved')->count(),
            'rejected' => $registrations->clone()->where('status', 'rejected')->count(),
            'checked_in' => $registrations->clone()->where('check_in_status', 'checked_in')->count(),
            'spots_remaining' => $tournament->max_teams - $tournament->current_team_count,
            'fill_percentage' => round(($tournament->current_team_count / $tournament->max_teams) * 100, 1),
        ];
    }
}