<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BracketSeeding extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id', 'event_id', 'bracket_stage_id', 'team_id',
        'seed', 'seeding_method', 'seeding_data', 'seeded_at'
    ];

    protected $casts = [
        'seeding_data' => 'array',
        'seeded_at' => 'datetime',
        'seed' => 'integer'
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Scopes
    public function scopeByMethod($query, $method)
    {
        return $query->where('seeding_method', $method);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('seed');
    }

    // Helper methods
    public function getSeedDisplayAttribute(): string
    {
        return "#{$this->seed}";
    }

    public function getMethodDisplayAttribute(): string
    {
        return match($this->seeding_method) {
            'manual' => 'Manual Seeding',
            'random' => 'Random Seeding',
            'rating' => 'Rating-Based',
            'previous_results' => 'Previous Results',
            default => ucfirst($this->seeding_method)
        };
    }

    // Constants
    public const SEEDING_METHODS = [
        'manual' => 'Manual Seeding',
        'random' => 'Random Seeding', 
        'rating' => 'Rating-Based Seeding',
        'previous_results' => 'Previous Tournament Results',
        'swiss_standings' => 'Swiss System Standings',
        'group_results' => 'Group Stage Results'
    ];
}