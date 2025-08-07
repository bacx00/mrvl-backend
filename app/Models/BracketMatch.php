<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BracketMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'tournament_id', 'event_id', 'bracket_stage_id', 
        'round_name', 'round_number', 'match_number',
        'team1_id', 'team2_id', 'team1_source', 'team2_source',
        'team1_score', 'team2_score', 'winner_id', 'loser_id',
        'status', 'best_of', 'scheduled_at', 'started_at', 'completed_at',
        'winner_advances_to', 'loser_advances_to', 'vods', 'interviews', 'notes',
        'bracket_reset'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'vods' => 'array',
        'interviews' => 'array',
        'bracket_reset' => 'boolean',
        'team1_score' => 'integer',
        'team2_score' => 'integer',
        'round_number' => 'integer',
        'match_number' => 'integer'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bracketStage(): BelongsTo
    {
        return $this->belongsTo(BracketStage::class);
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'loser_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(BracketGame::class)->orderBy('game_number');
    }

    public function position(): HasOne
    {
        return $this->hasOne(BracketPosition::class);
    }

    public function winnerAdvancesTo(): HasOne
    {
        return $this->hasOne(BracketMatch::class, 'match_id', 'winner_advances_to');
    }

    public function loserAdvancesTo(): HasOne
    {
        return $this->hasOne(BracketMatch::class, 'match_id', 'loser_advances_to');
    }

    // Computed attributes
    public function getIsCompleteAttribute(): bool
    {
        return $this->status === 'completed' && $this->winner_id !== null;
    }

    public function getIsReadyAttribute(): bool
    {
        return $this->team1_id && $this->team2_id && $this->status === 'pending';
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'live';
    }

    public function getScoreDisplayAttribute(): string
    {
        if (!$this->is_complete) {
            return match($this->status) {
                'pending' => $this->is_ready ? 'Ready' : 'TBD',
                'live' => 'LIVE',
                default => $this->status
            };
        }
        return "{$this->team1_score} - {$this->team2_score}";
    }

    public function getMatchDisplayNameAttribute(): string
    {
        return "{$this->round_name} - Match {$this->match_number}";
    }

    public function getShortDisplayNameAttribute(): string
    {
        return "{$this->match_id}";
    }

    public function getWinnerDisplayAttribute(): ?string
    {
        if (!$this->winner_id) return null;
        
        $winner = $this->winner;
        return $winner ? $winner->name : 'TBD';
    }

    public function getBestOfDisplayAttribute(): string
    {
        return "Bo{$this->best_of}";
    }

    public function getRequiredWinsAttribute(): int
    {
        return ceil($this->best_of / 2);
    }

    public function getProgressPercentageAttribute(): int
    {
        if (!$this->is_complete && $this->games->count() === 0) return 0;
        
        $totalGames = $this->best_of;
        $playedGames = $this->games->count();
        
        return min(100, round(($playedGames / $totalGames) * 100));
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByRound($query, $round)
    {
        return $query->where('round_number', $round);
    }

    public function scopeByStage($query, $stageId)
    {
        return $query->where('bracket_stage_id', $stageId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'pending')
                    ->whereNotNull('team1_id')
                    ->whereNotNull('team2_id');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('round_number')->orderBy('match_number');
    }

    // Match control methods
    public function start(): bool
    {
        if (!$this->is_ready) return false;

        $this->update([
            'status' => 'live',
            'started_at' => now()
        ]);

        return true;
    }

    public function complete(?int $winnerId = null, ?int $loserId = null): bool
    {
        // Auto-determine winner if not provided
        if (!$winnerId && $this->team1_score !== $this->team2_score) {
            $winnerId = $this->team1_score > $this->team2_score ? $this->team1_id : $this->team2_id;
        }

        if (!$winnerId) return false;

        $loserId = $loserId ?: ($winnerId === $this->team1_id ? $this->team2_id : $this->team1_id);

        $this->update([
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
            'status' => 'completed',
            'completed_at' => now()
        ]);

        $this->advanceTeams();
        return true;
    }

    public function forfeit(int $forfeitingTeamId): bool
    {
        if (!in_array($forfeitingTeamId, [$this->team1_id, $this->team2_id])) {
            return false;
        }

        $winnerId = $forfeitingTeamId === $this->team1_id ? $this->team2_id : $this->team1_id;

        $this->update([
            'winner_id' => $winnerId,
            'loser_id' => $forfeitingTeamId,
            'status' => 'forfeit',
            'completed_at' => now()
        ]);

        $this->advanceTeams();
        return true;
    }

    public function reset(): bool
    {
        if ($this->status !== 'completed') return false;

        $this->update([
            'winner_id' => null,
            'loser_id' => null,
            'team1_score' => 0,
            'team2_score' => 0,
            'status' => $this->is_ready ? 'ready' : 'pending',
            'completed_at' => null
        ]);

        // Reset games as well
        $this->games()->delete();

        return true;
    }

    public function updateScore(int $team1Score, int $team2Score): bool
    {
        $this->update([
            'team1_score' => $team1Score,
            'team2_score' => $team2Score
        ]);

        // Auto-complete if someone reached required wins
        $requiredWins = $this->required_wins;
        if ($team1Score >= $requiredWins || $team2Score >= $requiredWins) {
            $this->complete();
        }

        return true;
    }

    // Team progression methods
    private function advanceTeams(): void
    {
        // Advance winner
        if ($this->winner_advances_to && $this->winner_id) {
            $nextMatch = self::where('match_id', $this->winner_advances_to)->first();
            if ($nextMatch) {
                $this->assignTeamToMatch($nextMatch, $this->winner_id, "Winner of {$this->match_id}");
            }
        }

        // Advance loser (for double elimination)
        if ($this->loser_advances_to && $this->loser_id) {
            $nextMatch = self::where('match_id', $this->loser_advances_to)->first();
            if ($nextMatch) {
                $this->assignTeamToMatch($nextMatch, $this->loser_id, "Loser of {$this->match_id}");
            }
        }
    }

    private function assignTeamToMatch(BracketMatch $match, int $teamId, string $source): void
    {
        if (!$match->team1_id) {
            $match->update([
                'team1_id' => $teamId, 
                'team1_source' => $source
            ]);
        } elseif (!$match->team2_id) {
            $match->update([
                'team2_id' => $teamId, 
                'team2_source' => $source
            ]);
        }
    }

    // Helper methods for bracket reset (Grand Finals)
    public function canResetBracket(): bool
    {
        return $this->bracketStage->type === 'grand_final' && 
               $this->is_complete &&
               !$this->bracket_reset;
    }

    public function resetBracket(): bool
    {
        if (!$this->canResetBracket()) return false;

        // Create bracket reset match
        $resetMatch = self::create([
            'match_id' => $this->match_id . '-Reset',
            'tournament_id' => $this->tournament_id,
            'event_id' => $this->event_id,
            'bracket_stage_id' => $this->bracket_stage_id,
            'round_name' => 'Grand Final - Bracket Reset',
            'round_number' => $this->round_number + 1,
            'match_number' => 1,
            'team1_id' => $this->loser_id, // Lower bracket champion
            'team2_id' => $this->winner_id, // Upper bracket champion
            'team1_source' => 'Lower Bracket Champion',
            'team2_source' => 'Upper Bracket Champion',
            'status' => 'ready',
            'best_of' => $this->best_of,
            'bracket_reset' => true
        ]);

        $this->update(['bracket_reset' => true]);

        return true;
    }

    // Constants
    public const STATUSES = [
        'pending' => 'Pending',
        'ready' => 'Ready',
        'live' => 'Live',
        'completed' => 'Completed',
        'forfeit' => 'Forfeit',
        'cancelled' => 'Cancelled'
    ];

    public const BEST_OF_OPTIONS = [
        '1' => 'Best of 1',
        '3' => 'Best of 3',
        '5' => 'Best of 5',
        '7' => 'Best of 7'
    ];
}