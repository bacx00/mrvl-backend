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
        'profile_picture_type', 'use_hero_as_avatar'
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
        ];
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = bcrypt($value);
    }

    public function forumThreads()
    {
        return $this->hasMany(ForumThread::class);
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
     * Get user statistics with caching
     */
    public function getStatsWithCache()
    {
        return Cache::remember(
            self::CACHE_PREFIX . "stats_{$this->id}",
            self::CACHE_DURATION / 2, // Cache stats for 30 minutes
            function () {
                return $this->calculateUserStats();
            }
        );
    }
    
    /**
     * Calculate user statistics (optimized with single query approach)
     */
    private function calculateUserStats()
    {
        // Single optimized query to get all comment counts
        $commentStats = DB::select("
            SELECT 
                'news_comments' as type,
                COUNT(*) as count
            FROM news_comments 
            WHERE user_id = ?
            UNION ALL
            SELECT 
                'match_comments' as type,
                COUNT(*) as count
            FROM match_comments 
            WHERE user_id = ?
        ", [$this->id, $this->id]);
        
        $newsComments = 0;
        $matchComments = 0;
        foreach ($commentStats as $stat) {
            if ($stat->type === 'news_comments') {
                $newsComments = $stat->count;
            } elseif ($stat->type === 'match_comments') {
                $matchComments = $stat->count;
            }
        }
        
        // Single optimized query to get forum stats
        $forumStats = DB::selectOne("
            SELECT 
                COALESCE(t.thread_count, 0) as threads,
                COALESCE(p.post_count, 0) as posts
            FROM (
                SELECT COUNT(*) as thread_count
                FROM forum_threads 
                WHERE user_id = ?
            ) t
            CROSS JOIN (
                SELECT COUNT(*) as post_count
                FROM forum_posts 
                WHERE user_id = ?
            ) p
        ", [$this->id, $this->id]) ?? (object)['threads' => 0, 'posts' => 0];
        
        // Single optimized query to get vote stats
        $voteStats = DB::selectOne("
            SELECT 
                SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as upvotes_given,
                SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as downvotes_given
            FROM votes 
            WHERE user_id = ?
        ", [$this->id]) ?? (object)['upvotes_given' => 0, 'downvotes_given' => 0];
        
        return [
            'comments' => [
                'news' => $newsComments,
                'matches' => $matchComments,
                'total' => $newsComments + $matchComments
            ],
            'forum' => [
                'threads' => $forumStats->threads,
                'posts' => $forumStats->posts,
                'total' => $forumStats->threads + $forumStats->posts
            ],
            'votes' => [
                'upvotes_given' => $voteStats->upvotes_given,
                'downvotes_given' => $voteStats->downvotes_given
            ]
        ];
    }
    
    /**
     * Clear all cache entries related to this user
     */
    public function clearUserCache()
    {
        Cache::forget(self::CACHE_PREFIX . "full_{$this->id}");
        Cache::forget(self::CACHE_PREFIX . "stats_{$this->id}");
        Cache::forget("hero_flair_{$this->hero_flair}");
        Cache::forget("team_exists_{$this->team_flair_id}");
        Cache::forget("hero_exists_{$this->hero_flair}");
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
}
