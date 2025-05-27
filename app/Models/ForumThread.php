<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ForumThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'content', 'user_id', 'category', 'replies', 'views',
        'pinned', 'locked', 'last_reply_at'
    ];

    protected $casts = [
        'pinned' => 'boolean',
        'locked' => 'boolean',
        'last_reply_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}