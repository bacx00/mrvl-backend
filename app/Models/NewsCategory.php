<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsCategory extends Model
{
    use HasFactory;

    protected $table = 'news_categories';
    
    protected $fillable = [
        'name', 
        'slug', 
        'description', 
        'color', 
        'icon', 
        'active', 
        'sort_order'
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer'
    ];

    public function news()
    {
        return $this->hasMany(News::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}