<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_match_id', 'bracket_stage_id', 'column_position', 'row_position', 'tier', 'visual_settings'
    ];

    protected $casts = [
        'visual_settings' => 'array',
        'column_position' => 'integer',
        'row_position' => 'integer',
        'tier' => 'integer'
    ];

    // Relationships
    public function bracketMatch(): BelongsTo
    {
        return $this->belongsTo(BracketMatch::class);
    }

    public function bracketStage(): BelongsTo
    {
        return $this->belongsTo(BracketStage::class);
    }

    // Computed attributes
    public function getCssPositionAttribute(): array
    {
        $settings = $this->visual_settings ?? [];
        return [
            'grid-column' => $this->column_position,
            'grid-row' => $this->row_position,
            'tier' => $this->tier,
            'custom' => $settings
        ];
    }

    public function getGridPositionAttribute(): string
    {
        return "col-{$this->column_position} row-{$this->row_position}";
    }

    public function getLevelDisplayAttribute(): string
    {
        return match($this->tier) {
            0 => 'Round 1',
            1 => 'Round 2', 
            2 => 'Quarterfinals',
            3 => 'Semifinals',
            4 => 'Finals',
            default => "Round " . ($this->tier + 1)
        };
    }

    // Scopes
    public function scopeByStage($query, $stageId)
    {
        return $query->where('bracket_stage_id', $stageId);
    }

    public function scopeByTier($query, $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('tier')->orderBy('row_position');
    }

    // Helper methods
    public function calculatePosition(int $roundNumber, int $matchNumber, string $bracketType = 'upper'): array
    {
        // Calculate grid position based on tournament structure
        $baseColumn = match($bracketType) {
            'upper' => $roundNumber,
            'lower' => $roundNumber + 10, // Offset for lower bracket
            'final' => 20,
            default => $roundNumber
        };

        $baseRow = $matchNumber;

        return [
            'column_position' => $baseColumn,
            'row_position' => $baseRow,
            'tier' => $roundNumber - 1
        ];
    }

    public function updatePosition(int $column, int $row, int $tier = null): bool
    {
        return $this->update([
            'column_position' => $column,
            'row_position' => $row,
            'tier' => $tier ?? $this->tier
        ]);
    }

    public function updateVisualSettings(array $settings): bool
    {
        $currentSettings = $this->visual_settings ?? [];
        return $this->update([
            'visual_settings' => array_merge($currentSettings, $settings)
        ]);
    }

    // Static helper for bracket positioning
    public static function generateDoubleEliminationPositions(int $teamCount): array
    {
        $upperRounds = ceil(log($teamCount, 2));
        $lowerRounds = ($upperRounds * 2) - 2;
        
        $positions = [];
        
        // Upper bracket positions
        for ($round = 1; $round <= $upperRounds; $round++) {
            $matchesInRound = $teamCount / pow(2, $round);
            for ($match = 1; $match <= $matchesInRound; $match++) {
                $positions[] = [
                    'bracket_type' => 'upper',
                    'round' => $round,
                    'match' => $match,
                    'column_position' => $round,
                    'row_position' => $match * 2 - 1,
                    'tier' => $round - 1
                ];
            }
        }
        
        // Lower bracket positions (more complex due to bracket structure)
        $lowerMatchNumber = 1;
        for ($round = 1; $round <= $lowerRounds; $round++) {
            // Calculate matches per round in lower bracket
            $matchesInRound = $round % 2 === 1 ? 
                $teamCount / pow(2, ceil($round / 2) + 1) : 
                $teamCount / pow(2, $round / 2 + 1);
            
            for ($match = 1; $match <= $matchesInRound; $match++) {
                $positions[] = [
                    'bracket_type' => 'lower',
                    'round' => $round,
                    'match' => $lowerMatchNumber++,
                    'column_position' => $round + 10, // Offset from upper bracket
                    'row_position' => $match * 2,
                    'tier' => $round - 1
                ];
            }
        }
        
        // Grand final position
        $positions[] = [
            'bracket_type' => 'final',
            'round' => 1,
            'match' => 1,
            'column_position' => 20,
            'row_position' => 1,
            'tier' => 99 // Special tier for finals
        ];
        
        return $positions;
    }

    // Constants for different bracket layouts
    public const BRACKET_TYPES = [
        'upper' => 'Upper Bracket',
        'lower' => 'Lower Bracket', 
        'final' => 'Grand Final',
        'third_place' => 'Third Place Match',
        'group' => 'Group Stage'
    ];

    public const TIER_NAMES = [
        0 => 'Round 1',
        1 => 'Round 2',
        2 => 'Round 3', 
        3 => 'Quarterfinals',
        4 => 'Semifinals',
        5 => 'Finals',
        99 => 'Grand Final'
    ];
}