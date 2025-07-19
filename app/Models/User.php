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
        return "/images/heroes/" . str_replace([' ', '&'], ['-', 'and'], strtolower($this->hero_flair)) . ".png";
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
                'fallback_text' => $this->teamFlair->short_name
            ];
        }
        
        return $flairs;
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
