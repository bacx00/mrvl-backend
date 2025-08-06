<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'last_login', 'status',
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

    public function teamFlair()
    {
        return $this->belongsTo(Team::class, 'team_flair_id');
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
     * Update user flairs with proper validation
     */
    public function updateFlairs($heroFlair = null, $teamFlairId = null, $showHero = null, $showTeam = null)
    {
        $updates = [];
        
        if ($heroFlair !== null) {
            // Validate hero exists
            $heroExists = \DB::table('marvel_rivals_heroes')
                ->where('name', $heroFlair)
                ->exists();
                
            if ($heroExists || $heroFlair === '') {
                $updates['hero_flair'] = $heroFlair ?: null;
            }
        }
        
        if ($teamFlairId !== null) {
            // Validate team exists
            $teamExists = \DB::table('teams')
                ->where('id', $teamFlairId)
                ->exists();
                
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
            
            // Track activity
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
        
        return $this->fresh(['teamFlair']);
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
}
