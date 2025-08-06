<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BracketMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'bracket_stage_id', 'match_id', 'round_name', 'round_number', 'match_number',
        'team1_id', 'team2_id', 'team1_source', 'team2_source',
        'team1_score', 'team2_score', 'winner_id', 'loser_id',
        'status', 'best_of', 'scheduled_at', 'started_at', 'completed_at',
        'winner_advances_to', 'loser_advances_to', 'vods', 'interviews', 'notes'
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'vods' => 'array',
        'interviews' => 'array'
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function bracketStage()
    {
        return $this->belongsTo(BracketStage::class);
    }

    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winner()
    {
        return $this->belongsTo(Team::class, 'winner_id');
    }

    public function loser()
    {
        return $this->belongsTo(Team::class, 'loser_id');
    }

    public function games()
    {
        return $this->hasMany(BracketGame::class)->orderBy('game_number');
    }

    public function position()
    {
        return $this->hasOne(BracketPosition::class);
    }

    public function winnerAdvancesTo()
    {
        return $this->hasOne(BracketMatch::class, 'match_id', 'winner_advances_to');
    }

    public function loserAdvancesTo()
    {
        return $this->hasOne(BracketMatch::class, 'match_id', 'loser_advances_to');
    }

    public function getIsCompleteAttribute()
    {
        return $this->status === 'completed' && $this->winner_id !== null;
    }

    public function getScoreDisplayAttribute()
    {
        if (!$this->is_complete) {
            return $this->status === 'pending' ? 'TBD' : 'LIVE';
        }
        return "{$this->team1_score} - {$this->team2_score}";
    }

    public function getMatchDisplayNameAttribute()
    {
        return "{$this->round_name} - Match {$this->match_number}";
    }

    // Auto-advance teams when match is completed
    public function completeMatch($winnerId, $loserId = null)
    {
        $this->update([
            'winner_id' => $winnerId,
            'loser_id' => $loserId ?: ($winnerId === $this->team1_id ? $this->team2_id : $this->team1_id),
            'status' => 'completed',
            'completed_at' => now()
        ]);

        $this->advanceTeams();
    }

    private function advanceTeams()
    {
        // Advance winner
        if ($this->winner_advances_to && $this->winner_id) {
            $nextMatch = BracketMatch::where('match_id', $this->winner_advances_to)->first();
            if ($nextMatch) {
                $this->assignTeamToMatch($nextMatch, $this->winner_id, "winner_of_{$this->match_id}");
            }
        }

        // Advance loser (for double elimination)
        if ($this->loser_advances_to && $this->loser_id) {
            $nextMatch = BracketMatch::where('match_id', $this->loser_advances_to)->first();
            if ($nextMatch) {
                $this->assignTeamToMatch($nextMatch, $this->loser_id, "loser_of_{$this->match_id}");
            }
        }
    }

    private function assignTeamToMatch($match, $teamId, $source)
    {
        if (!$match->team1_id) {
            $match->update(['team1_id' => $teamId, 'team1_source' => $source]);
        } elseif (!$match->team2_id) {
            $match->update(['team2_id' => $teamId, 'team2_source' => $source]);
        }
    }
}