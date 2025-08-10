<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'type', 'format', 'status', 'description', 'region',
        'prize_pool', 'currency', 'team_count', 'max_teams', 'min_teams',
        'start_date', 'end_date', 'registration_start', 'registration_end',
        'check_in_start', 'check_in_end', 'settings', 'rules', 'timezone',
        'organizer_id', 'logo', 'banner', 'featured', 'public', 'views',
        'current_phase', 'phase_data', 'bracket_data', 'seeding_data',
        'qualification_settings', 'map_pool', 'match_format_settings',
        'stream_urls', 'discord_url', 'social_links', 'contact_info'
    ];

    protected $casts = [
        'prize_pool' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'registration_start' => 'datetime',
        'registration_end' => 'datetime',
        'check_in_start' => 'datetime',
        'check_in_end' => 'datetime',
        'settings' => 'array',
        'rules' => 'array',
        'phase_data' => 'array',
        'bracket_data' => 'array',
        'seeding_data' => 'array',
        'qualification_settings' => 'array',
        'map_pool' => 'array',
        'match_format_settings' => 'array',
        'stream_urls' => 'array',
        'social_links' => 'array',
        'contact_info' => 'array',
        'featured' => 'boolean',
        'public' => 'boolean',
        'views' => 'integer',
        'team_count' => 'integer',
        'max_teams' => 'integer',
        'min_teams' => 'integer'
    ];

    protected $attributes = [
        'status' => 'draft',
        'format' => 'double_elimination',
        'type' => 'tournament',
        'region' => 'global',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'max_teams' => 16,
        'min_teams' => 4,
        'featured' => false,
        'public' => true,
        'views' => 0,
        'current_phase' => 'registration'
    ];

    // Tournament Types (following Liquipedia structure)
    public const TYPES = [
        'mrc' => 'Marvel Rivals Championship',
        'mri' => 'Marvel Rivals Invitational', 
        'ignite' => 'Marvel Rivals Ignite',
        'community' => 'Community Tournament',
        'qualifier' => 'Qualifier',
        'regional' => 'Regional Championship',
        'international' => 'International Championship',
        'showmatch' => 'Show Match',
        'scrim' => 'Scrimmage'
    ];

    // Tournament Formats
    public const FORMATS = [
        'single_elimination' => 'Single Elimination',
        'double_elimination' => 'Double Elimination', 
        'swiss' => 'Swiss System',
        'round_robin' => 'Round Robin',
        'group_stage_playoffs' => 'Group Stage → Playoffs',
        'ladder' => 'Ladder Tournament'
    ];

    // Tournament Status
    public const STATUSES = [
        'draft' => 'Draft',
        'registration_open' => 'Registration Open',
        'registration_closed' => 'Registration Closed',
        'check_in' => 'Check-in Period',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'postponed' => 'Postponed'
    ];

    // Tournament Phases
    public const PHASES = [
        'registration' => 'Registration',
        'check_in' => 'Check-in',
        'open_qualifier_1' => 'Open Qualifier #1',
        'open_qualifier_2' => 'Open Qualifier #2',
        'closed_qualifier' => 'Closed Qualifier',
        'group_stage' => 'Group Stage',
        'swiss_rounds' => 'Swiss Rounds',
        'upper_bracket' => 'Upper Bracket',
        'lower_bracket' => 'Lower Bracket',
        'playoffs' => 'Playoffs',
        'grand_final' => 'Grand Final',
        'completed' => 'Completed'
    ];

    // Match Formats
    public const MATCH_FORMATS = [
        'bo1' => 'Best of 1',
        'bo3' => 'Best of 3', 
        'bo5' => 'Best of 5',
        'bo7' => 'Best of 7'
    ];

    // Relationships
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(TournamentPhase::class)->orderBy('phase_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TournamentBracket::class);
    }

    public function bracketStages(): HasMany
    {
        return $this->hasMany(BracketStage::class)->orderBy('stage_order');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'tournament_teams')
                    ->withPivot([
                        'seed', 'status', 'registered_at', 'checked_in_at',
                        'swiss_wins', 'swiss_losses', 'swiss_score', 'swiss_buchholz',
                        'group_id', 'bracket_position', 'elimination_round',
                        'prize_money', 'placement', 'points_earned'
                    ])
                    ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByFormat($query, $format)
    {
        return $query->where('format', $format);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopePublic($query)
    {
        return $query->where('public', true);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', ['draft', 'registration_open', 'registration_closed', 'check_in']);
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getFormattedPrizePoolAttribute()
    {
        if (!$this->prize_pool) return null;
        return number_format($this->prize_pool, 0) . ' ' . $this->currency;
    }

    public function getRegistrationOpenAttribute()
    {
        $now = now($this->timezone);
        return $this->registration_start <= $now && 
               $this->registration_end >= $now && 
               in_array($this->status, ['registration_open']);
    }

    public function getCheckInOpenAttribute()
    {
        if (!$this->check_in_start || !$this->check_in_end) return false;
        
        $now = now($this->timezone);
        return $this->check_in_start <= $now && 
               $this->check_in_end >= $now &&
               $this->status === 'check_in';
    }

    public function getCurrentTeamCountAttribute()
    {
        return $this->teams()->wherePivot('status', '!=', 'disqualified')->count();
    }

    public function getCheckedInTeamsCountAttribute()
    {
        return $this->teams()->wherePivot('status', 'checked_in')->count();
    }

    // Tournament Management Methods
    public function canRegisterTeam(): bool
    {
        return $this->registration_open && 
               $this->current_team_count < $this->max_teams;
    }

    public function registerTeam(Team $team, array $additionalData = []): bool
    {
        if (!$this->canRegisterTeam()) return false;

        $this->teams()->attach($team->id, array_merge([
            'status' => 'registered',
            'registered_at' => now(),
        ], $additionalData));

        $this->increment('team_count');
        return true;
    }

    public function checkInTeam(Team $team): bool
    {
        if (!$this->check_in_open) return false;

        $this->teams()->updateExistingPivot($team->id, [
            'status' => 'checked_in',
            'checked_in_at' => now()
        ]);

        return true;
    }

    public function startTournament(): bool
    {
        if ($this->current_team_count < $this->min_teams) return false;

        $this->status = 'ongoing';
        $this->current_phase = $this->getInitialPhase();
        $this->save();

        return true;
    }

    public function getInitialPhase(): string
    {
        switch ($this->format) {
            case 'swiss':
                return 'swiss_rounds';
            case 'group_stage_playoffs':
                return 'group_stage';
            case 'single_elimination':
            case 'double_elimination':
                return $this->current_team_count > 8 ? 'open_qualifier_1' : 'playoffs';
            default:
                return 'playoffs';
        }
    }

    public function progressToNextPhase(): bool
    {
        $phases = array_keys(self::PHASES);
        $currentIndex = array_search($this->current_phase, $phases);
        
        if ($currentIndex !== false && $currentIndex < count($phases) - 1) {
            $this->current_phase = $phases[$currentIndex + 1];
            $this->save();
            return true;
        }

        return false;
    }

    // Swiss System Methods
    public function getSwissStandingsAttribute()
    {
        return $this->teams()
                    ->orderByDesc('pivot_swiss_score')
                    ->orderByDesc('pivot_swiss_buchholz')  // Tiebreaker
                    ->orderByDesc('pivot_swiss_wins')
                    ->orderBy('pivot_swiss_losses')
                    ->get();
    }

    public function getSwissQualifiedTeamsAttribute()
    {
        $settings = $this->qualification_settings ?? [];
        $winsRequired = $settings['swiss_wins_required'] ?? 3;
        
        return $this->teams()
                    ->wherePivot('swiss_wins', '>=', $winsRequired)
                    ->orderByDesc('pivot_swiss_score')
                    ->get();
    }

    public function getSwissEliminatedTeamsAttribute()
    {
        $settings = $this->qualification_settings ?? [];
        $lossesEliminated = $settings['swiss_losses_eliminated'] ?? 3;
        
        return $this->teams()
                    ->wherePivot('swiss_losses', '>=', $lossesEliminated)
                    ->get();
    }

    // Double Elimination Bracket Methods
    public function getUpperBracketTeamsAttribute()
    {
        return $this->teams()
                    ->whereNull('pivot_elimination_round') // Still in upper bracket
                    ->orderBy('pivot_seed')
                    ->get();
    }

    public function getLowerBracketTeamsAttribute()
    {
        return $this->teams()
                    ->whereNotNull('pivot_elimination_round')
                    ->orderBy('pivot_elimination_round')
                    ->orderBy('pivot_seed')
                    ->get();
    }

    // Match Format Methods
    public function getMatchFormatForRound(string $round): string
    {
        $formats = $this->match_format_settings ?? [];
        
        return $formats[$round] ?? $formats['default'] ?? 'bo3';
    }

    public function calculateBuchholzScore(Team $team): float
    {
        // Buchholz score: sum of opponents' Swiss scores
        $opponents = $this->getTeamOpponents($team);
        return $opponents->sum('pivot_swiss_score');
    }

    public function getTeamOpponents(Team $team)
    {
        // Get all teams this team has played against
        $playedMatches = $this->matches()
            ->where(function($query) use ($team) {
                $query->where('team1_id', $team->id)
                      ->orWhere('team2_id', $team->id);
            })
            ->where('status', 'completed')
            ->get();

        $opponentIds = $playedMatches->flatMap(function($match) use ($team) {
            return $match->team1_id === $team->id ? [$match->team2_id] : [$match->team1_id];
        })->unique();

        return $this->teams()->whereIn('teams.id', $opponentIds)->get();
    }

    // Utility Methods
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function isLive(): bool
    {
        return $this->status === 'ongoing';
    }

    public function hasStarted(): bool
    {
        return !in_array($this->status, ['draft', 'registration_open', 'registration_closed', 'check_in']);
    }

    public function hasEnded(): bool
    {
        return in_array($this->status, ['completed', 'cancelled']);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'registration_open']);
    }

    public function canDelete(): bool
    {
        return $this->status === 'draft' && $this->current_team_count === 0;
    }

    public function getProgressPercentage(): int
    {
        $phases = array_keys(self::PHASES);
        $currentIndex = array_search($this->current_phase, $phases);
        
        if ($currentIndex === false) return 0;
        
        return (int) round(($currentIndex / (count($phases) - 1)) * 100);
    }

    public function getDurationInDays(): int
    {
        if (!$this->start_date || !$this->end_date) return 0;
        
        return $this->start_date->diffInDays($this->end_date);
    }

    public function getTimeUntilStart(): ?string
    {
        if (!$this->start_date || $this->hasStarted()) return null;
        
        return $this->start_date->diffForHumans();
    }

    public function getTimeUntilEnd(): ?string
    {
        if (!$this->end_date || $this->hasEnded()) return null;
        
        return $this->end_date->diffForHumans();
    }
}