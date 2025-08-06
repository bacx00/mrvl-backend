<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BracketPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'bracket_match_id', 'bracket_stage_id', 'column_position', 'row_position', 'tier', 'visual_settings'
    ];

    protected $casts = [
        'visual_settings' => 'array'
    ];

    public function bracketMatch()
    {
        return $this->belongsTo(BracketMatch::class);
    }

    public function bracketStage()
    {
        return $this->belongsTo(BracketStage::class);
    }

    public function getCssPositionAttribute()
    {
        $settings = $this->visual_settings ?? [];
        return [
            'grid-column' => $this->column_position,
            'grid-row' => $this->row_position,
            'tier' => $this->tier,
            'custom' => $settings
        ];
    }
}