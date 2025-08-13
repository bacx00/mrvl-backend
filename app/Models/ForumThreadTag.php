<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ForumThreadTag extends Pivot
{
    protected $table = 'forum_thread_tags';
    
    protected $fillable = [
        'thread_id',
        'tag_id',
        'assigned_by',
        'auto_assigned'
    ];

    protected $casts = [
        'auto_assigned' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get the thread this tag is assigned to
     */
    public function thread()
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /**
     * Get the tag
     */
    public function tag()
    {
        return $this->belongsTo(ForumTag::class, 'tag_id');
    }

    /**
     * Get the user who assigned this tag
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope for auto-assigned tags
     */
    public function scopeAutoAssigned($query)
    {
        return $query->where('auto_assigned', true);
    }

    /**
     * Scope for manually assigned tags
     */
    public function scopeManuallyAssigned($query)
    {
        return $query->where('auto_assigned', false);
    }
}