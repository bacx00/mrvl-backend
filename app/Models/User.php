<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Notifications\ResetPasswordNotification;
use App\Services\OptimizedUserProfileService;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * Cache duration for frequently accessed data (in seconds)
     */
    const CACHE_DURATION = 3600; // 1 hour
    
    /**
     * Cache key prefix for user-related cache entries
     */
    const CACHE_PREFIX = 'user_profile_';

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'last_login', 'status', 'role',
        'hero_flair', 'team_flair_id', 'show_hero_flair', 'show_team_flair',
        'profile_picture_type', 'use_hero_as_avatar', 'banned_at', 'ban_reason',
        'ban_expires_at', 'muted_until', 'last_activity',
        'mention_count', 'last_mentioned_at'
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'show_hero_flair' => true,
        'show_team_flair' => false,
        'use_hero_as_avatar' => false,
        'mention_count' => 0
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'show_hero_flair' => 'boolean',
            'show_team_flair' => 'boolean',
            'use_hero_as_avatar' => 'boolean',
            'banned_at' => 'datetime',
            'ban_expires_at' => 'datetime',
            'muted_until' => 'datetime',
            'last_activity' => 'datetime',
            'last_mentioned_at' => 'datetime',
            'warning_count' => 'integer',
            'mention_count' => 'integer'
        ];
    }

    public function forumThreads()
    {
        return $this->hasMany(ForumThread::class);
    }

    public function forumPosts()
    {
        return $this->hasMany(Post::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function moderatedReports()
    {
        return $this->hasMany(Report::class, 'moderator_id');
    }

    public function warnings()
    {
        return $this->hasMany(UserWarning::class);
    }

    public function issuedWarnings()
    {
        return $this->hasMany(UserWarning::class, 'moderator_id');
    }

    public function reportsAgainst()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /**
     * Team flair relationship with eager loading optimization
     */
    public function teamFlair()
    {
        return $this->belongsTo(Team::class, 'team_flair_id')
            ->select(['id', 'name', 'short_name', 'logo', 'region']); // Only load needed columns
    }

    /**
     * Teams relationship - teams this user is part of
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_players', 'user_id', 'team_id')
                    ->withTimestamps()
                    ->withPivot('position', 'status', 'joined_at');
    }

    /**
     * Followed tournaments relationship
     */
    public function followedTournaments()
    {
        return $this->belongsToMany(Tournament::class, 'tournament_followers')
                    ->withTimestamps();
    }
    
    /**
     * Hero flair relationship (virtual)
     */
    public function heroFlair()
    {
        if (!$this->hero_flair) {
            return null;
        }
        
        // Use cached hero data to avoid repeated queries
        return Cache::remember(
            "hero_flair_{$this->hero_flair}",
            self::CACHE_DURATION,
            function () {
                return DB::table('marvel_rivals_heroes')
                    ->where('name', $this->hero_flair)
                    ->select(['id', 'name', 'slug', 'role', 'image_url'])
                    ->first();
            }
        );
    }

    public function getHeroFlairImageAttribute()
    {
        if (!$this->hero_flair || !$this->show_hero_flair) {
            return null;
        }
        
        // Return hero image URL based on hero name
        // This will fallback to text if image not available
        return "/images/heroes/" . str_replace([' ', '&'], ['-', 'and'], strtolower($this->hero_flair)) . "-headbig.webp";
    }

    public function getDisplayFlairsAttribute()
    {
        $flairs = [];
        
        if ($this->show_hero_flair && $this->hero_flair) {
            $flairs['hero'] = [
                'type' => 'hero',
                'name' => $this->hero_flair,
                'image' => $this->hero_flair_image,
                'fallback_text' => $this->hero_flair
            ];
        }
        
        if ($this->show_team_flair && $this->teamFlair) {
            $flairs['team'] = [
                'type' => 'team',
                'name' => $this->teamFlair->name,
                'short_name' => $this->teamFlair->short_name,
                'image' => $this->teamFlair->logo,
                'fallback_text' => $this->teamFlair->short_name,
                'region' => $this->teamFlair->region
            ];
        }
        
        return $flairs;
    }

    /**
     * Update user flairs with proper validation and caching optimization
     */
    public function updateFlairs($heroFlair = null, $teamFlairId = null, $showHero = null, $showTeam = null)
    {
        $updates = [];
        
        if ($heroFlair !== null) {
            // Use cached validation for hero existence
            $heroExists = Cache::remember(
                "hero_exists_{$heroFlair}",
                self::CACHE_DURATION,
                function () use ($heroFlair) {
                    return DB::table('marvel_rivals_heroes')
                        ->where('name', $heroFlair)
                        ->exists();
                }
            );
                
            if ($heroExists || $heroFlair === '') {
                $updates['hero_flair'] = $heroFlair ?: null;
            }
        }
        
        if ($teamFlairId !== null) {
            // Use cached validation for team existence
            $teamExists = Cache::remember(
                "team_exists_{$teamFlairId}",
                self::CACHE_DURATION,
                function () use ($teamFlairId) {
                    return DB::table('teams')
                        ->where('id', $teamFlairId)
                        ->exists();
                }
            );
                
            if ($teamExists || $teamFlairId === '') {
                $updates['team_flair_id'] = $teamFlairId ?: null;
            }
        }
        
        if ($showHero !== null) {
            $updates['show_hero_flair'] = (bool)$showHero;
        }
        
        if ($showTeam !== null) {
            $updates['show_team_flair'] = (bool)$showTeam;
        }
        
        if (!empty($updates)) {
            $this->update($updates);
            
            // Clear user-related cache entries
            $this->clearUserCache();
            
            // Track activity if UserActivity model exists
            if (class_exists('\\App\\Models\\UserActivity')) {
                \App\Models\UserActivity::track(
                    $this->id,
                    'flairs_updated',
                    'Updated profile flairs',
                    'user',
                    $this->id,
                    [
                        'hero_flair' => $updates['hero_flair'] ?? $this->hero_flair,
                        'team_flair_id' => $updates['team_flair_id'] ?? $this->team_flair_id,
                        'show_hero_flair' => $updates['show_hero_flair'] ?? $this->show_hero_flair,
                        'show_team_flair' => $updates['show_team_flair'] ?? $this->show_team_flair,
                    ]
                );
            }
        }
        
        return $this->fresh(['teamFlair']);
    }
    
    /**
     * Get user profile with optimized queries and caching
     */
    public function getProfileWithCache()
    {
        return Cache::remember(
            self::CACHE_PREFIX . "full_{$this->id}",
            self::CACHE_DURATION,
            function () {
                return $this->load([
                    'teamFlair' => function ($query) {
                        $query->select(['id', 'name', 'short_name', 'logo', 'region']);
                    }
                ]);
            }
        );
    }
    
    /**
     * Get user statistics with caching (using optimized service)
     */
    public function getStatsWithCache()
    {
        $service = app(OptimizedUserProfileService::class);
        return $service->getUserStatisticsOptimized($this->id);
    }
    
    /**
     * Calculate user statistics (highly optimized single query approach)
     */
    private function calculateUserStats()
    {
        // Ultra-optimized single query to get all user statistics at once
        $allStats = DB::selectOne("
            SELECT 
                -- Comment counts using conditional aggregation
                COUNT(CASE WHEN nc.id IS NOT NULL THEN 1 END) as news_comments,
                COUNT(CASE WHEN mc.id IS NOT NULL THEN 1 END) as match_comments,
                
                -- Forum counts using conditional aggregation  
                COUNT(CASE WHEN ft.id IS NOT NULL THEN 1 END) as forum_threads,
                COUNT(CASE WHEN fp.id IS NOT NULL THEN 1 END) as forum_posts,
                
                -- Vote counts using conditional aggregation
                COUNT(CASE WHEN v.vote = 1 THEN 1 END) as upvotes_given,
                COUNT(CASE WHEN v.vote = -1 THEN 1 END) as downvotes_given
                
            FROM users u
            LEFT JOIN news_comments nc ON nc.user_id = u.id
            LEFT JOIN match_comments mc ON mc.user_id = u.id  
            LEFT JOIN forum_threads ft ON ft.user_id = u.id
            LEFT JOIN forum_posts fp ON fp.user_id = u.id
            LEFT JOIN votes v ON v.user_id = u.id
            WHERE u.id = ?
        ", [$this->id]);
        
        if (!$allStats) {
            // Fallback if user doesn't exist
            $allStats = (object)[
                'news_comments' => 0,
                'match_comments' => 0, 
                'forum_threads' => 0,
                'forum_posts' => 0,
                'upvotes_given' => 0,
                'downvotes_given' => 0
            ];
        }
        
        return [
            'comments' => [
                'news' => $allStats->news_comments,
                'matches' => $allStats->match_comments,
                'total' => $allStats->news_comments + $allStats->match_comments
            ],
            'forum' => [
                'threads' => $allStats->forum_threads,
                'posts' => $allStats->forum_posts,
                'total' => $allStats->forum_threads + $allStats->forum_posts
            ],
            'votes' => [
                'upvotes_given' => $allStats->upvotes_given,
                'downvotes_given' => $allStats->downvotes_given
            ]
        ];
    }
    
    /**
     * Clear all cache entries related to this user (using optimized service)
     */
    public function clearUserCache()
    {
        // Clear traditional cache entries
        Cache::forget(self::CACHE_PREFIX . "full_{$this->id}");
        Cache::forget(self::CACHE_PREFIX . "stats_{$this->id}");
        Cache::forget("hero_flair_{$this->hero_flair}");
        Cache::forget("team_exists_{$this->team_flair_id}");
        Cache::forget("hero_exists_{$this->hero_flair}");
        
        // Clear optimized service cache entries
        $service = app(OptimizedUserProfileService::class);
        $service->clearUserCaches($this->id);
    }
    
    /**
     * Boot method to handle cache invalidation on model events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Clear cache when user is updated
        static::updated(function ($user) {
            $user->clearUserCache();
        });
        
        // Clear cache when user is deleted
        static::deleted(function ($user) {
            $user->clearUserCache();
        });
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Role-based access control methods
     */
    
    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a moderator or higher
     */
    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin']);
    }

    /**
     * Check if user is a regular user
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get user's role display name
     */
    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'moderator' => 'Moderator',
            'user' => 'User',
            default => ucfirst($this->role)
        };
    }

    /**
     * Achievement system relationships
     */

    /**
     * Get user achievements
     */
    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }

    /**
     * Get user's earned achievements
     */
    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot(['progress', 'current_count', 'required_count', 'is_completed', 'completed_at', 'completion_count'])
            ->withTimestamps();
    }

    /**
     * Get user streaks
     */
    public function streaks()
    {
        return $this->hasMany(UserStreak::class);
    }

    /**
     * Get user challenges
     */
    public function userChallenges()
    {
        return $this->hasMany(UserChallenge::class);
    }

    /**
     * Get user's participated challenges
     */
    public function challenges()
    {
        return $this->belongsToMany(Challenge::class, 'user_challenges')
            ->withPivot(['progress', 'current_score', 'is_completed', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * Get user titles
     */
    public function titles()
    {
        return $this->hasMany(UserTitle::class);
    }

    /**
     * Get user's active title
     */
    public function activeTitle()
    {
        return $this->hasOne(UserTitle::class)->where('is_active', true);
    }

    /**
     * Get user achievement notifications
     */
    public function achievementNotifications()
    {
        return $this->hasMany(AchievementNotification::class);
    }

    /**
     * Get leaderboard entries
     */
    public function leaderboardEntries()
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * Get user's achievement summary with caching
     */
    public function getAchievementSummaryAttribute(): array
    {
        return Cache::remember(
            "user_achievement_summary_{$this->id}",
            300,
            function () {
                $service = app(\App\Services\AchievementService::class);
                return $service->getUserAchievementSummary($this->id);
            }
        );
    }

    // ===================================
    // FORUM MODERATION METHODS
    // ===================================

    /**
     * Check if the user is banned
     */
    public function isBanned(): bool
    {
        if (!$this->banned_at) {
            return false;
        }

        // If ban has expiration date, check if it's still active
        if ($this->ban_expires_at && $this->ban_expires_at->isPast()) {
            // Auto-unban expired bans
            $this->update([
                'banned_at' => null,
                'ban_reason' => null,
                'ban_expires_at' => null
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if the user is muted
     */
    public function isMuted(): bool
    {
        return $this->muted_until && $this->muted_until->isFuture();
    }

    /**
     * Check if the user has active warnings
     */
    public function hasActiveWarnings(): bool
    {
        // Check if expires_at column exists
        if (\Schema::hasColumn('user_warnings', 'expires_at')) {
            return $this->warnings()
                        ->where(function ($q) {
                            $q->where('expires_at', '>', now())
                              ->orWhereNull('expires_at');
                        })
                        ->exists();
        }
        // If expires_at doesn't exist, just check if any warnings exist
        return $this->warnings()->exists();
    }

    /**
     * Get active warnings
     */
    public function getActiveWarnings()
    {
        return $this->warnings()
                    ->where(function ($q) {
                        $q->where('expires_at', '>', now())
                          ->orWhereNull('expires_at');
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();
    }

    /**
     * Ban the user
     */
    public function ban(string $reason, ?\DateTime $expiresAt = null): void
    {
        $this->update([
            'banned_at' => now(),
            'ban_reason' => $reason,
            'ban_expires_at' => $expiresAt
        ]);
    }

    /**
     * Unban the user
     */
    public function unban(): void
    {
        $this->update([
            'banned_at' => null,
            'ban_reason' => null,
            'ban_expires_at' => null
        ]);
    }

    /**
     * Mute the user
     */
    public function mute(\DateTime $until): void
    {
        $this->update(['muted_until' => $until]);
    }

    /**
     * Unmute the user
     */
    public function unmute(): void
    {
        $this->update(['muted_until' => null]);
    }

    /**
     * Add a warning to the user
     */
    public function warn(int $moderatorId, string $reason, string $severity = 'medium', ?\DateTime $expiresAt = null): UserWarning
    {
        $warning = $this->warnings()->create([
            'moderator_id' => $moderatorId,
            'reason' => $reason,
            'severity' => $severity,
            'expires_at' => $expiresAt
        ]);

        $this->increment('warning_count');

        return $warning;
    }

    /**
     * Update last activity timestamp
     */
    public function updateLastActivity(): void
    {
        $this->update(['last_activity' => now()]);
    }

    /**
     * Get user's moderation status
     */
    public function getModerationStatusAttribute(): array
    {
        return [
            'is_banned' => $this->isBanned(),
            'is_muted' => $this->isMuted(),
            'has_warnings' => $this->hasActiveWarnings(),
            'warning_count' => \Schema::hasColumn('users', 'warning_count') ? 
                $this->warning_count : 
                $this->warnings()->count(),
            'ban_reason' => $this->ban_reason,
            'ban_expires_at' => $this->ban_expires_at,
            'muted_until' => $this->muted_until
        ];
    }

    /**
     * Check if user can perform forum actions
     */
    public function canPostInForum(): bool
    {
        return !$this->isBanned() && !$this->isMuted();
    }

    /**
     * Check if user is a moderator or admin
     */
    public function canModerate(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    /**
     * Get forum engagement stats
     */
    public function getForumEngagementStatsAttribute(): array
    {
        return Cache::remember(
            "user_forum_stats_{$this->id}",
            300,
            function () {
                return [
                    'threads_created' => $this->forumThreads()->count(),
                    'posts_created' => $this->forumPosts()->count(),
                    'reports_filed' => $this->reports()->count(),
                    'reports_received' => $this->reportsAgainst()->count(),
                    'warnings_received' => $this->warnings()->count(),
                    'total_thread_views' => $this->forumThreads()->sum('views'),
                    'total_thread_replies' => $this->forumThreads()->sum('replies')
                ];
            }
        );
    }

    /**
     * Scope for active users (not banned)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('banned_at')
                    ->orWhere(function ($q) {
                        $q->whereNotNull('ban_expires_at')
                          ->where('ban_expires_at', '<=', now());
                    });
    }

    /**
     * Scope for banned users
     */
    public function scopeBanned($query)
    {
        return $query->whereNotNull('banned_at')
                    ->where(function ($q) {
                        $q->whereNull('ban_expires_at')
                          ->orWhere('ban_expires_at', '>', now());
                    });
    }

    /**
     * Scope for muted users
     */
    public function scopeMuted($query)
    {
        return $query->where('muted_until', '>', now());
    }

    /**
     * Scope for users with warnings
     */
    public function scopeWithWarnings($query)
    {
        if (\Schema::hasColumn('users', 'warning_count')) {
            return $query->where('warning_count', '>', 0);
        }
        return $query->whereHas('warnings');
    }

    /**
     * Mention-related relationships and methods
     */

    /**
     * Get mentions of this user
     */
    public function mentions()
    {
        return $this->morphMany(Mention::class, 'mentioned', 'mentioned_type', 'mentioned_id');
    }

    /**
     * Get mentions created by this user
     */
    public function createdMentions()
    {
        return $this->hasMany(Mention::class, 'mentioned_by');
    }

    /**
     * Get active mentions of this user with optimized query
     */
    public function activeMentions()
    {
        return $this->mentions()->where('is_active', true);
    }

    /**
     * Get recent mentions with pagination support
     */
    public function getRecentMentions($limit = 10, $offset = 0)
    {
        return Cache::remember(
            "user_mentions_{$this->id}_{$limit}_{$offset}",
            300, // 5 minutes cache
            function () use ($limit, $offset) {
                return $this->activeMentions()
                    ->with(['mentionable'])
                    ->orderBy('mentioned_at', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Get mention count efficiently
     */
    public function getMentionCount(): int
    {
        // Use the denormalized column if available
        if (isset($this->attributes['mention_count'])) {
            return (int) $this->attributes['mention_count'];
        }
        
        // Fallback to counting if column doesn't exist
        return $this->activeMentions()->count();
    }

    /**
     * Scope for users with mentions above threshold
     */
    public function scopePopularMentions($query, $threshold = 5)
    {
        if (\Schema::hasColumn('users', 'mention_count')) {
            return $query->where('mention_count', '>=', $threshold);
        }
        
        return $query->whereHas('mentions', function ($q) use ($threshold) {
            $q->where('is_active', true)
              ->havingRaw('COUNT(*) >= ?', [$threshold]);
        });
    }

    /**
     * Scope for recently mentioned users
     */
    public function scopeRecentlyMentioned($query, $days = 7)
    {
        if (\Schema::hasColumn('users', 'last_mentioned_at')) {
            return $query->where('last_mentioned_at', '>=', now()->subDays($days));
        }
        
        return $query->whereHas('mentions', function ($q) use ($days) {
            $q->where('is_active', true)
              ->where('mentioned_at', '>=', now()->subDays($days));
        });
    }

    /**
     * Get mention analytics data
     */
    public function getMentionAnalytics(): array
    {
        return Cache::remember(
            "user_mention_analytics_{$this->id}",
            self::CACHE_DURATION,
            function () {
                $mentions = $this->activeMentions()
                    ->selectRaw('
                        COUNT(*) as total_mentions,
                        COUNT(DISTINCT mentionable_type) as content_types,
                        COUNT(DISTINCT mentioned_by) as unique_mentioners,
                        MAX(mentioned_at) as last_mention,
                        MIN(mentioned_at) as first_mention
                    ')
                    ->first();

                $contentBreakdown = $this->activeMentions()
                    ->selectRaw('mentionable_type, COUNT(*) as count')
                    ->groupBy('mentionable_type')
                    ->pluck('count', 'mentionable_type')
                    ->toArray();

                return [
                    'total_mentions' => $mentions->total_mentions ?? 0,
                    'content_types' => $mentions->content_types ?? 0,
                    'unique_mentioners' => $mentions->unique_mentioners ?? 0,
                    'last_mention' => $mentions->last_mention,
                    'first_mention' => $mentions->first_mention,
                    'content_breakdown' => $contentBreakdown
                ];
            }
        );
    }

    /**
     * Clear mention-related cache
     */
    public function clearMentionCache(): void
    {
        $patterns = [
            "user_mentions_{$this->id}_*",
            "user_mention_analytics_{$this->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::tags(['user_mentions', "user_{$this->id}"])->flush();
        }
    }
}
