<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TournamentPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'name', 'slug', 'phase_type', 'phase_order', 'status',
        'description', 'start_date', 'end_date', 'settings', 'bracket_data',
        'seeding_method', 'team_count', 'advancement_count', 'elimination_count',
        'match_format', 'map_pool', 'is_active', 'completed_at', 'results_data'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'completed_at' => 'datetime',
        'settings' => 'array',
        'bracket_data' => 'array',
        'results_data' => 'array',
        'map_pool' => 'array',
        'is_active' => 'boolean',
        'phase_order' => 'integer',
        'team_count' => 'integer',
        'advancement_count' => 'integer',
        'elimination_count' => 'integer'
    ];

    protected $attributes = [
        'status' => 'pending',
        'phase_order' => 1,
        'is_active' => false,
        'match_format' => 'bo3',
        'seeding_method' => 'random'
    ];

    // Phase Types
    public const PHASE_TYPES = [
        'registration' => 'Registration',
        'check_in' => 'Check-in',
        'open_qualifier' => 'Open Qualifier',
        'closed_qualifier' => 'Closed Qualifier',
        'group_stage' => 'Group Stage',
        'swiss_rounds' => 'Swiss Rounds',
        'upper_bracket' => 'Upper Bracket',
        'lower_bracket' => 'Lower Bracket',
        'playoffs' => 'Playoffs',
        'semifinals' => 'Semifinals',
        'grand_final' => 'Grand Final'
    ];

    // Phase Status
    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];

    // Seeding Methods
    public const SEEDING_METHODS = [
        'random' => 'Random Seeding',
        'elo_based' => 'ELO-based Seeding',
        'previous_phase' => 'Previous Phase Results',
        'manual' => 'Manual Seeding',
        'swiss_standings' => 'Swiss Standings',
        'group_standings' => 'Group Standings'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BracketMatch::class);
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(TournamentBracket::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('phase_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('phase_order');
    }

    // Accessors
    public function getFormattedNameAttribute()
    {
        return self::PHASE_TYPES[$this->phase_type] ?? $this->name;
    }

    public function getIsCurrentPhaseAttribute()
    {
        return $this->is_active && $this->status === 'active';
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->status === 'completed') return 100;
        if ($this->status === 'pending') return 0;

        $totalMatches = $this->matches()->count();
        if ($totalMatches === 0) return 0;

        $completedMatches = $this->matches()->where('status', 'completed')->count();
        return round(($completedMatches / $totalMatches) * 100, 1);
    }

    public function getDurationAttribute()
    {
        if (!$this->start_date || !$this->end_date) return null;
        return $this->start_date->diffForHumans($this->end_date, true);
    }

    // Phase Management Methods
    public function startPhase(): bool
    {
        if ($this->status !== 'pending') return false;

        $this->status = 'active';
        $this->is_active = true;
        $this->start_date = now();
        $this->save();

        // Deactivate other phases in the same tournament
        $this->tournament->phases()
             ->where('id', '!=', $this->id)
             ->update(['is_active' => false]);

        return true;
    }

    public function completePhase(array $resultsData = []): bool
    {
        if ($this->status !== 'active') return false;

        $this->status = 'completed';
        $this->is_active = false;
        $this->completed_at = now();
        $this->end_date = now();
        $this->results_data = $resultsData;
        $this->save();

        return true;
    }

    public function canStart(): bool
    {
        return $this->status === 'pending' && 
               $this->hasRequiredTeams() &&
               $this->isPreviousPhaseCompleted();
    }

    public function hasRequiredTeams(): bool
    {
        if ($this->team_count === 0) return true; // No specific requirement
        
        $availableTeams = $this->getAvailableTeams()->count();
        return $availableTeams >= $this->team_count;
    }

    public function isPreviousPhaseCompleted(): bool
    {
        if ($this->phase_order === 1) return true; // First phase
        
        $previousPhase = $this->tournament->phases()
                              ->where('phase_order', '<', $this->phase_order)
                              ->orderByDesc('phase_order')
                              ->first();

        return $previousPhase ? $previousPhase->status === 'completed' : true;
    }

    public function getAvailableTeams()
    {
        switch ($this->seeding_method) {
            case 'previous_phase':
                return $this->getAdvancingTeamsFromPreviousPhase();
            
            case 'swiss_standings':
                return $this->tournament->swiss_qualified_teams;
            
            case 'group_standings':
                return $this->getTeamsFromGroupStage();
            
            default:
                return $this->tournament->teams()
                           ->wherePivot('status', 'checked_in')
                           ->get();
        }
    }

    private function getAdvancingTeamsFromPreviousPhase()
    {
        $previousPhase = $this->tournament->phases()
                              ->where('phase_order', '<', $this->phase_order)
                              ->where('status', 'completed')
                              ->orderByDesc('phase_order')
                              ->first();

        if (!$previousPhase || !$previousPhase->advancement_count) {
            return collect();
        }

        // Get top teams from previous phase results
        $results = $previousPhase->results_data ?? [];
        $advancingTeamIds = array_slice($results['advancing_teams'] ?? [], 0, $previousPhase->advancement_count);

        return $this->tournament->teams()->whereIn('teams.id', $advancingTeamIds)->get();
    }

    private function getTeamsFromGroupStage()
    {
        // Get teams that advanced from group stage
        // This would depend on your group stage implementation
        return $this->tournament->teams()
                   ->wherePivot('status', 'advanced')
                   ->get();
    }

    public function generateBracket(): bool
    {
        if ($this->status !== 'pending') return false;

        $teams = $this->getAvailableTeams();
        if ($teams->count() < 2) return false;

        $bracketGenerator = new \App\Services\BracketGenerationService();
        
        switch ($this->phase_type) {
            case 'swiss_rounds':
                $bracket = $bracketGenerator->generateSwissBracket($teams, $this->settings);
                break;
                
            case 'upper_bracket':
            case 'lower_bracket':
            case 'playoffs':
                $bracket = $bracketGenerator->generateEliminationBracket($teams, $this->settings);
                break;
                
            case 'group_stage':
                $bracket = $bracketGenerator->generateGroupStageBracket($teams, $this->settings);
                break;
                
            default:
                return false;
        }

        if ($bracket) {
            $this->bracket_data = $bracket;
            $this->save();
            return true;
        }

        return false;
    }

    public function seedTeams(array $teamIds = null): bool
    {
        $teams = $teamIds ? 
                 $this->tournament->teams()->whereIn('teams.id', $teamIds)->get() :
                 $this->getAvailableTeams();

        if ($teams->isEmpty()) return false;

        switch ($this->seeding_method) {
            case 'elo_based':
                $seeded = $teams->sortByDesc('elo_rating');
                break;
                
            case 'swiss_standings':
                $seeded = $teams->sortByDesc('pivot_swiss_score');
                break;
                
            case 'previous_phase':
                // Teams are already ordered from previous phase
                $seeded = $teams;
                break;
                
            case 'random':
            default:
                $seeded = $teams->shuffle();
                break;
        }

        // Update seeding in tournament_teams pivot table
        foreach ($seeded->values() as $index => $team) {
            $this->tournament->teams()->updateExistingPivot($team->id, [
                'seed' => $index + 1
            ]);
        }

        return true;
    }

    public function getSeededTeams()
    {
        return $this->tournament->teams()
                   ->orderBy('pivot_seed')
                   ->get();
    }

    public function getRemainingMatches()
    {
        return $this->matches()
                   ->whereIn('status', ['pending', 'ongoing'])
                   ->get();
    }

    public function getCompletedMatches()
    {
        return $this->matches()
                   ->where('status', 'completed')
                   ->get();
    }

    public function isComplete(): bool
    {
        return $this->status === 'completed' || 
               $this->getRemainingMatches()->count() === 0;
    }

    public function canAdvanceToNextPhase(): bool
    {
        return $this->isComplete() && 
               $this->advancement_count > 0 &&
               $this->getAdvancingTeams()->count() >= $this->advancement_count;
    }

    public function getAdvancingTeams()
    {
        // Get teams that advance to the next phase based on this phase's results
        $results = $this->results_data ?? [];
        
        if (!empty($results['advancing_teams'])) {
            $teamIds = array_slice($results['advancing_teams'], 0, $this->advancement_count);
            return $this->tournament->teams()->whereIn('teams.id', $teamIds)->get();
        }

        // Fallback: get top teams based on phase type
        switch ($this->phase_type) {
            case 'swiss_rounds':
                return $this->tournament->swiss_qualified_teams->take($this->advancement_count);
                
            case 'group_stage':
                // Implement group stage advancement logic
                return collect();
                
            default:
                // For elimination brackets, get winners
                return $this->getWinningTeams()->take($this->advancement_count);
        }
    }

    public function getEliminatedTeams()
    {
        $results = $this->results_data ?? [];
        
        if (!empty($results['eliminated_teams'])) {
            $teamIds = array_slice($results['eliminated_teams'], 0, $this->elimination_count);
            return $this->tournament->teams()->whereIn('teams.id', $teamIds)->get();
        }

        // Fallback logic for different phase types
        switch ($this->phase_type) {
            case 'swiss_rounds':
                return $this->tournament->swiss_eliminated_teams;
                
            default:
                return $this->getLosingTeams()->take($this->elimination_count);
        }
    }

    private function getWinningTeams()
    {
        // Get teams that won their matches in this phase
        return $this->tournament->teams()
                   ->whereHas('homeMatches', function($query) {
                       $query->where('tournament_phase_id', $this->id)
                             ->where('status', 'completed')
                             ->whereColumn('team1_score', '>', 'team2_score');
                   })
                   ->orWhereHas('awayMatches', function($query) {
                       $query->where('tournament_phase_id', $this->id)
                             ->where('status', 'completed')
                             ->whereColumn('team2_score', '>', 'team1_score');
                   });
    }

    private function getLosingTeams()
    {
        // Get teams that lost their matches in this phase
        return $this->tournament->teams()
                   ->whereHas('homeMatches', function($query) {
                       $query->where('tournament_phase_id', $this->id)
                             ->where('status', 'completed')
                             ->whereColumn('team1_score', '<', 'team2_score');
                   })
                   ->orWhereHas('awayMatches', function($query) {
                       $query->where('tournament_phase_id', $this->id)
                             ->where('status', 'completed')
                             ->whereColumn('team2_score', '<', 'team1_score');
                   });
    }

    public function calculateResults(): array
    {
        $completedMatches = $this->getCompletedMatches();
        $advancingTeams = [];
        $eliminatedTeams = [];
        $standings = [];

        switch ($this->phase_type) {
            case 'swiss_rounds':
                $standings = $this->calculateSwissStandings();
                $advancingTeams = $standings['advancing'];
                $eliminatedTeams = $standings['eliminated'];
                break;

            case 'group_stage':
                $standings = $this->calculateGroupStandings();
                $advancingTeams = $standings['advancing'];
                break;

            case 'upper_bracket':
            case 'lower_bracket':
            case 'playoffs':
                $results = $this->calculateEliminationResults();
                $advancingTeams = $results['winners'];
                $eliminatedTeams = $results['losers'];
                break;
        }

        return [
            'phase_id' => $this->id,
            'phase_name' => $this->name,
            'matches_played' => $completedMatches->count(),
            'advancing_teams' => $advancingTeams,
            'eliminated_teams' => $eliminatedTeams,
            'standings' => $standings,
            'completed_at' => now()->toDateTimeString()
        ];
    }

    private function calculateSwissStandings(): array
    {
        $teams = $this->tournament->teams;
        $standings = [];

        foreach ($teams as $team) {
            $wins = $team->homeMatches()
                         ->where('tournament_phase_id', $this->id)
                         ->where('status', 'completed')
                         ->whereColumn('team1_score', '>', 'team2_score')
                         ->count() +
                    $team->awayMatches()
                         ->where('tournament_phase_id', $this->id)
                         ->where('status', 'completed')
                         ->whereColumn('team2_score', '>', 'team1_score')
                         ->count();

            $losses = $team->homeMatches()
                          ->where('tournament_phase_id', $this->id)
                          ->where('status', 'completed')
                          ->whereColumn('team1_score', '<', 'team2_score')
                          ->count() +
                      $team->awayMatches()
                          ->where('tournament_phase_id', $this->id)
                          ->where('status', 'completed')
                          ->whereColumn('team2_score', '<', 'team1_score')
                          ->count();

            $score = $wins * 3; // 3 points per win
            $buchholz = $this->tournament->calculateBuchholzScore($team);

            $standings[] = [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'wins' => $wins,
                'losses' => $losses,
                'score' => $score,
                'buchholz' => $buchholz
            ];
        }

        // Sort by score, then buchholz, then wins
        usort($standings, function($a, $b) {
            if ($a['score'] === $b['score']) {
                if ($a['buchholz'] === $b['buchholz']) {
                    return $b['wins'] - $a['wins'];
                }
                return $b['buchholz'] <=> $a['buchholz'];
            }
            return $b['score'] - $a['score'];
        });

        $winsRequired = $this->settings['swiss_wins_required'] ?? 3;
        $lossesEliminated = $this->settings['swiss_losses_eliminated'] ?? 3;

        $advancing = [];
        $eliminated = [];

        foreach ($standings as $standing) {
            if ($standing['wins'] >= $winsRequired) {
                $advancing[] = $standing['team_id'];
            } elseif ($standing['losses'] >= $lossesEliminated) {
                $eliminated[] = $standing['team_id'];
            }
        }

        return [
            'standings' => $standings,
            'advancing' => $advancing,
            'eliminated' => $eliminated
        ];
    }

    private function calculateGroupStandings(): array
    {
        // Implement group stage standings calculation
        // This would depend on your specific group stage format
        return [
            'standings' => [],
            'advancing' => []
        ];
    }

    private function calculateEliminationResults(): array
    {
        $completedMatches = $this->getCompletedMatches();
        $winners = [];
        $losers = [];

        foreach ($completedMatches as $match) {
            if ($match->team1_score > $match->team2_score) {
                $winners[] = $match->team1_id;
                $losers[] = $match->team2_id;
            } else {
                $winners[] = $match->team2_id;
                $losers[] = $match->team1_id;
            }
        }

        return [
            'winners' => array_unique($winners),
            'losers' => array_unique($losers)
        ];
    }
}