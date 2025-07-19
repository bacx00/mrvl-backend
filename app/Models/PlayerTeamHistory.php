<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTeamHistory extends Model
{
    protected $table = 'player_team_history';
    
    protected $fillable = [
        'player_id',
        'from_team_id',
        'to_team_id',
        'change_date',
        'change_type',
        'reason',
        'notes',
        'transfer_fee',
        'currency',
        'is_official',
        'source_url',
        'announced_by'
    ];

    protected $casts = [
        'change_date' => 'datetime',
        'transfer_fee' => 'decimal:2',
        'is_official' => 'boolean',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }

    public function announcedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'announced_by');
    }

    // Helper methods
    public function getFormattedTransferFeeAttribute()
    {
        if (!$this->transfer_fee) return null;
        return number_format($this->transfer_fee, 0) . ' ' . $this->currency;
    }

    public function getChangeDescriptionAttribute()
    {
        $descriptions = [
            'joined' => 'joined',
            'left' => 'left',
            'transferred' => 'transferred to',
            'released' => 'was released from',
            'retired' => 'retired from',
            'loan_start' => 'loaned to',
            'loan_end' => 'returned from loan at'
        ];

        $action = $descriptions[$this->change_type] ?? $this->change_type;
        
        if ($this->to_team) {
            return "{$action} {$this->toTeam->name}";
        } elseif ($this->from_team) {
            return "{$action} {$this->fromTeam->name}";
        }
        
        return $action;
    }
}
