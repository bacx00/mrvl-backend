<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'logo', 'banner',
        'type', 'tier', 'format', 'region', 'game_mode',
        'status', 'start_date', 'end_date', 'registration_start', 'registration_end', 'timezone',
        'max_teams', 'organizer_id',
        'prize_pool', 'currency', 'prize_distribution',
        'rules', 'registration_requirements', 'streams', 'social_links',
        'featured', 'public', 'views',
        'bracket_data', 'seeding_data', 'current_round', 'total_rounds'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'prize_pool' => 'decimal:2',
        'prize_distribution' => 'array',
        'registration_requirements' => 'array',
        'streams' => 'array',
        'social_links' => 'array',
        'bracket_data' => 'array',
        'seeding_data' => 'array',
        'featured' => 'boolean',
        'public' => 'boolean',
        'views' => 'integer',
        'max_teams' => 'integer',
        'current_round' => 'integer',
        'total_rounds' => 'integer'
    ];

    protected $attributes = [
        'status' => 'upcoming',
        'tier' => 'B',
        'format' => 'single_elimination',
        'game_mode' => '5v5',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'region' => 'International',
        'description' => '',
        'max_teams' => 16,
        'featured' => false,
        'public' => true,
        'views' => 0,
        'current_round' => 0,
        'total_rounds' => 0
    ];

    // Relationships
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function teams(): BelongsToMany
    {
        $pivotColumns = ['seed', 'status', 'registered_at'];
        
        // Only include registration_data if column exists
        if (\Schema::hasColumn('event_teams', 'registration_data')) {
            $pivotColumns[] = 'registration_data';
        }
        
        return $this->belongsToMany(Team::class, 'event_teams')
                    ->withPivot($pivotColumns)
                    ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MvrlMatch::class, 'event_id');
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(Bracket::class);
    }

    public function standings(): HasMany
    {
        return $this->hasMany(EventStanding::class);
    }

    public function bracketStages(): HasMany
    {
        return $this->hasMany(BracketStage::class);
    }

    public function bracketMatches(): HasMany
    {
        return $this->hasMany(BracketMatch::class);
    }

    public function bracketStandings(): HasMany
    {
        return $this->hasMany(BracketStanding::class);
    }

    // Scopes
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopePublic($query)
    {
        return $query->where('public', true);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')->where('start_date', '>', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing')
                    ->orWhere(function($q) {
                        $q->where('start_date', '<=', now())
                          ->where('end_date', '>=', now());
                    });
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed')->orWhere('end_date', '<', now());
    }

    // Accessors & Mutators
    public function getFormattedPrizePoolAttribute()
    {
        if (!$this->prize_pool) return null;
        
        return number_format($this->prize_pool, 0) . ' ' . $this->currency;
    }

    public function getRegistrationOpenAttribute()
    {
        $now = now();
        return $this->registration_start <= $now && 
               $this->registration_end >= $now && 
               $this->status === 'upcoming';
    }

    public function getCurrentTeamCountAttribute()
    {
        return $this->teams()->count();
    }

    public function getCompletedMatchesCountAttribute()
    {
        return $this->matches()->where('status', 'completed')->count();
    }

    public function getTotalMatchesCountAttribute()
    {
        return $this->matches()->count();
    }

    public function getProgressPercentageAttribute()
    {
        $total = $this->total_matches_count;
        if ($total === 0) return 0;
        
        return round(($this->completed_matches_count / $total) * 100, 1);
    }

    // Helper Methods
    public function isRegistrationOpen(): bool
    {
        return $this->registration_open;
    }

    public function canRegisterTeam(): bool
    {
        return $this->isRegistrationOpen() && 
               $this->current_team_count < $this->max_teams;
    }

    public function hasStarted(): bool
    {
        return now() >= $this->start_date;
    }

    public function hasEnded(): bool
    {
        return now() >= $this->end_date;
    }

    public function isLive(): bool
    {
        return $this->status === 'ongoing' || 
               ($this->hasStarted() && !$this->hasEnded());
    }

    public function calculateTotalRounds(): int
    {
        switch ($this->format) {
            case 'single_elimination':
                return $this->current_team_count > 0 ? 
                       ceil(log($this->current_team_count, 2)) : 0;
            
            case 'double_elimination':
                $teams = $this->current_team_count;
                return $teams > 0 ? ceil(log($teams, 2)) * 2 - 1 : 0;
            
            case 'swiss':
                return $this->current_team_count > 0 ? 
                       ceil(log($this->current_team_count, 2)) : 0;
            
            case 'round_robin':
                return $this->current_team_count > 0 ? 
                       $this->current_team_count - 1 : 0;
            
            default:
                return 0;
        }
    }

    public function generateBracket(): bool
    {
        if ($this->current_team_count < 2) {
            return false;
        }

        $this->total_rounds = $this->calculateTotalRounds();
        $this->current_round = 1;
        $this->status = 'ongoing';
        $this->save();

        return true;
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    // Tournament Format Constants
    public const TYPES = [
        'championship' => 'Championship',
        'tournament' => 'Tournament',
        'scrim' => 'Scrimmage',
        'qualifier' => 'Qualifier',
        'regional' => 'Regional',
        'international' => 'International',
        'invitational' => 'Invitational',
        'community' => 'Community',
        'friendly' => 'Friendly',
        'practice' => 'Practice',
        'exhibition' => 'Exhibition'
    ];

    public const TIERS = [
        'S' => 'S-Tier (Premier)',
        'A' => 'A-Tier (Major)',
        'B' => 'B-Tier (Standard)',
        'C' => 'C-Tier (Minor)'
    ];

    public const FORMATS = [
        'single_elimination' => 'Single Elimination',
        'double_elimination' => 'Double Elimination',
        'round_robin' => 'Round Robin',
        'swiss' => 'Swiss System',
        'group_stage' => 'Group Stage',
        'gsl' => 'GSL Format',
        'king_of_the_hill' => 'King of the Hill',
        'ladder' => 'Ladder',
        'gauntlet' => 'Gauntlet',
        'battle_royale' => 'Battle Royale',
        'bo1' => 'Best of 1',
        'bo3' => 'Best of 3',
        'bo5' => 'Best of 5',
        'bo7' => 'Best of 7',
        'bo9' => 'Best of 9'
    ];

    public const STATUSES = [
        'upcoming' => 'Upcoming',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];

    public const GAME_MODES = [
        'convoy' => 'Convoy',
        'domination' => 'Domination',
        'convergence' => 'Convergence',
        'clash' => 'Clash',
        'custom' => 'Custom',
        'competitive' => 'Competitive',
        'tournament' => 'Tournament'
    ];
}