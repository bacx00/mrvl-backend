<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Player;
use App\Models\MatchModel;
use App\Models\News;
use App\Models\ForumThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OptimizedQueryService
{
    /**
     * Optimized teams listing with proper eager loading and caching
     */
    public function getTeams(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $cacheKey = 'teams_list_' . md5(serialize($filters)) . '_' . $perPage;
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage) {
            $query = Team::select([
                'id', 'name', 'short_name', 'logo', 'region', 'platform', 
                'game', 'division', 'country', 'rating', 'rank', 'win_rate', 
                'points', 'record', 'peak', 'streak', 'founded', 'captain', 
                'coach', 'coach_name', 'website', 'earnings', 'social_media', 
                'achievements', 'recent_form', 'player_count', 'status'
            ]);

            // Apply filters efficiently using indexes
            if (!empty($filters['region']) && $filters['region'] !== 'all') {
                $query->where('region', $filters['region']);
            }

            if (!empty($filters['platform']) && $filters['platform'] !== 'all') {
                $query->where('platform', $filters['platform']);
            }

            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('name', 'LIKE', "%{$filters['search']}%")
                      ->orWhere('short_name', 'LIKE', "%{$filters['search']}%");
                });
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Optimized ordering using composite index
            return $query->orderBy('rating', 'desc')
                         ->orderBy('rank', 'asc')
                         ->paginate($perPage);
        });
    }

    /**
     * Optimized players listing with eager loading
     */
    public function getPlayers(array $filters = [], int $perPage = 100): LengthAwarePaginator
    {
        $cacheKey = 'players_list_' . md5(serialize($filters)) . '_' . $perPage;
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $perPage) {
            $query = Player::select([
                'id', 'username', 'real_name', 'role', 'avatar', 
                'rating', 'main_hero', 'country', 'age', 'status', 'team_id'
            ])->with(['team:id,name,short_name,logo']);

            // Apply filters efficiently using indexes
            if (!empty($filters['role']) && $filters['role'] !== 'all') {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['team']) && $filters['team'] !== 'all') {
                $query->where('team_id', $filters['team']);
            }

            if (!empty($filters['region']) && $filters['region'] !== 'all') {
                $query->where('region', $filters['region']);
            }

            if (!empty($filters['search'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('username', 'LIKE', "%{$filters['search']}%")
                      ->orWhere('real_name', 'LIKE', "%{$filters['search']}%");
                });
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            // Use composite index for performance
            return $query->orderBy('rating', 'desc')
                         ->paginate($perPage);
        });
    }

    /**
     * Optimized team detail with controlled eager loading
     */
    public function getTeamDetail(int $teamId): ?Team
    {
        $cacheKey = "team_detail_{$teamId}";
        
        return Cache::remember($cacheKey, 600, function () use ($teamId) {
            return Team::with([
                'players' => function($query) {
                    $query->select('id', 'username', 'real_name', 'role', 'avatar', 'rating', 'team_id')
                          ->where('status', 'active')
                          ->orderBy('rating', 'desc');
                }
            ])->find($teamId);
        });
    }

    /**
     * Optimized player detail with selective eager loading
     */
    public function getPlayerDetail(int $playerId): ?Player
    {
        $cacheKey = "player_detail_{$playerId}";
        
        return Cache::remember($cacheKey, 600, function () use ($playerId) {
            return Player::with([
                'team:id,name,short_name,logo,region',
                'teamHistory' => function($query) {
                    $query->with(['fromTeam:id,name,short_name', 'toTeam:id,name,short_name'])
                          ->orderBy('change_date', 'desc')
                          ->limit(10);
                }
            ])->find($playerId);
        });
    }

    /**
     * Optimized matches listing with eager loading
     */
    public function getMatches(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $cacheKey = 'matches_list_' . md5(serialize($filters)) . '_' . $perPage;
        
        return Cache::remember($cacheKey, 180, function () use ($filters, $perPage) {
            $query = MatchModel::with([
                'team1:id,name,short_name,logo',
                'team2:id,name,short_name,logo',
                'event:id,name,tier'
            ]);

            // Apply filters using indexes
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['event_id'])) {
                $query->where('event_id', $filters['event_id']);
            }

            if (!empty($filters['team_id'])) {
                $query->where(function($q) use ($filters) {
                    $q->where('team1_id', $filters['team_id'])
                      ->orWhere('team2_id', $filters['team_id']);
                });
            }

            if (!empty($filters['date_from'])) {
                $query->where('scheduled_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('scheduled_at', '<=', $filters['date_to']);
            }

            // Use composite index for optimal performance
            return $query->orderBy('status', 'desc')
                         ->orderBy('scheduled_at', 'desc')
                         ->paginate($perPage);
        });
    }

    /**
     * Optimized live matches for admin dashboard
     */
    public function getLiveMatches(): \Illuminate\Support\Collection
    {
        return Cache::remember('live_matches', 60, function () {
            return MatchModel::with([
                'team1:id,name,short_name,logo',
                'team2:id,name,short_name,logo',
                'event:id,name'
            ])
            ->whereIn('status', ['live', 'upcoming'])
            ->orderBy('status', 'desc')
            ->orderBy('scheduled_at', 'asc')
            ->limit(20)
            ->get();
        });
    }

    /**
     * Optimized admin dashboard stats with single queries
     */
    public function getAdminDashboardStats(): array
    {
        return Cache::remember('admin_dashboard_stats', 300, function () {
            // Single efficient queries using raw SQL for better performance
            $stats = [
                'users' => DB::selectOne("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN DATE(last_login) = DATE('now') THEN 1 END) as active_today,
                        COUNT(CASE WHEN created_at >= DATE('now', '-7 days') THEN 1 END) as new_this_week
                    FROM users
                "),
                'teams' => DB::selectOne("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active
                    FROM teams
                "),
                'players' => DB::selectOne("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active
                    FROM players
                "),
                'matches' => DB::selectOne("
                    SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'live' THEN 1 END) as live,
                        COUNT(CASE WHEN status = 'upcoming' THEN 1 END) as upcoming,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                        COUNT(CASE WHEN DATE(scheduled_at) = DATE('now') THEN 1 END) as today
                    FROM matches
                ")
            ];

            // Team distribution by region using grouped query
            $stats['teams_by_region'] = DB::select("
                SELECT region, COUNT(*) as count 
                FROM teams 
                WHERE region IS NOT NULL
                GROUP BY region 
                ORDER BY count DESC
            ");

            // Player distribution by role using grouped query
            $stats['players_by_role'] = DB::select("
                SELECT role, COUNT(*) as count 
                FROM players 
                WHERE role IS NOT NULL
                GROUP BY role 
                ORDER BY count DESC
            ");

            return $stats;
        });
    }

    /**
     * Optimized team matches with pagination
     */
    public function getTeamMatches(int $teamId, int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = "team_matches_{$teamId}_{$perPage}";
        
        return Cache::remember($cacheKey, 300, function () use ($teamId, $perPage) {
            return MatchModel::with([
                'team1:id,name,short_name,logo',
                'team2:id,name,short_name,logo',
                'event:id,name'
            ])
            ->where(function($query) use ($teamId) {
                $query->where('team1_id', $teamId)
                      ->orWhere('team2_id', $teamId);
            })
            ->orderBy('scheduled_at', 'desc')
            ->paginate($perPage);
        });
    }

    /**
     * Optimized player statistics with aggregation
     */
    public function getPlayerStats(int $playerId, array $dateRange = []): array
    {
        $cacheKey = 'player_stats_' . $playerId . '_' . md5(serialize($dateRange));
        
        return Cache::remember($cacheKey, 900, function () use ($playerId, $dateRange) {
            $query = DB::table('match_player_stats as mps')
                ->join('matches as m', 'mps.match_id', '=', 'm.id')
                ->where('mps.player_id', $playerId)
                ->where('m.status', 'completed');

            if (!empty($dateRange['from'])) {
                $query->where('m.scheduled_at', '>=', $dateRange['from']);
            }
            if (!empty($dateRange['to'])) {
                $query->where('m.scheduled_at', '<=', $dateRange['to']);
            }

            return $query->selectRaw("
                COUNT(*) as matches_played,
                AVG(mps.performance_rating) as avg_rating,
                AVG(mps.combat_score) as avg_combat_score,
                AVG(mps.kda) as avg_kda,
                SUM(mps.eliminations) as total_eliminations,
                SUM(mps.deaths) as total_deaths,
                SUM(mps.assists) as total_assists
            ")->first();
        });
    }

    /**
     * Clear related caches when data changes
     */
    public function clearTeamCaches(int $teamId): void
    {
        $patterns = [
            "team_detail_{$teamId}",
            "team_matches_{$teamId}*",
            "teams_list_*",
            "admin_dashboard_stats"
        ];

        foreach ($patterns as $pattern) {
            if (strpos($pattern, '*') !== false) {
                // Clear pattern-based cache keys
                Cache::flush(); // In production, use more specific cache tag clearing
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Clear player-related caches
     */
    public function clearPlayerCaches(int $playerId): void
    {
        $patterns = [
            "player_detail_{$playerId}",
            "player_stats_{$playerId}*",
            "players_list_*",
            "admin_dashboard_stats"
        ];

        foreach ($patterns as $pattern) {
            if (strpos($pattern, '*') !== false) {
                Cache::flush();
            } else {
                Cache::forget($pattern);
            }
        }
    }

    /**
     * Optimized news listing with eager loading
     */
    public function getNews(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = 'news_list_' . md5(serialize($filters)) . '_' . $perPage;
        
        return Cache::remember($cacheKey, 600, function () use ($filters, $perPage) {
            $query = News::with(['category:id,name'])
                ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'status', 
                         'published_at', 'category_id', 'featured']);

            if (!empty($filters['category_id'])) {
                $query->where('category_id', $filters['category_id']);
            }

            if (!empty($filters['featured'])) {
                $query->where('featured', true);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            } else {
                $query->where('status', 'published');
            }

            return $query->orderBy('featured', 'desc')
                         ->orderBy('published_at', 'desc')
                         ->paginate($perPage);
        });
    }
}