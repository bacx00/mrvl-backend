<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'bracket_type', 'round', 'position', 
        'round_name', 'match_id', 'bracket_data'
    ];

    protected $casts = [
        'bracket_data' => 'array',
        'round' => 'integer',
        'position' => 'integer'
    ];

    // Relationships
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Match::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('bracket_type', $type);
    }

    public function scopeByRound($query, $round)
    {
        return $query->where('round', $round);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('round')->orderBy('position');
    }

    // Constants
    public const BRACKET_TYPES = [
        'main' => 'Main Bracket',
        'upper' => 'Upper Bracket',
        'lower' => 'Lower Bracket',
        'group_a' => 'Group A',
        'group_b' => 'Group B',
        'group_c' => 'Group C',
        'group_d' => 'Group D'
    ];
}