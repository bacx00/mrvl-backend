<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'content',
        'resource_type',
        'resource_id',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper method to track an activity
    public static function track($userId, $action, $content, $resourceType = null, $resourceId = null, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'content' => $content,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata
        ]);
    }
}