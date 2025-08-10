<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForumCategory extends Model
{
    use HasFactory;

    protected $table = 'forum_categories';
    
    protected $fillable = [
        'name', 
        'slug', 
        'description', 
        'color', 
        'icon', 
        'is_active', 
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function threads()
    {
        return $this->hasMany(ForumThread::class, 'category_id');
    }

    public function posts()
    {
        return $this->hasManyThrough(Post::class, ForumThread::class, 'category_id', 'thread_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}