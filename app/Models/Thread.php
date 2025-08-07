<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use HasFactory;

    protected $table = 'forum_threads';
    
    protected $fillable = ['title', 'user_id', 'content', 'category_id'];

    // Hide the old category field to prevent conflicts
    protected $hidden = ['category'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function forumCategory()
    {
        return $this->belongsTo(ForumCategory::class, 'category_id');
    }

    // Accessor to get category name for backward compatibility
    public function getCategoryNameAttribute()
    {
        return $this->forumCategory ? $this->forumCategory->name : null;
    }
}