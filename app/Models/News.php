<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_image', 'gallery', 'videos',
        'category', 'category_id', 'tags', 'author_id', 'status', 'published_at', 
        'featured', 'featured_at', 'breaking', 'sort_order', 'meta_data', 'score',
        'upvotes', 'downvotes', 'comments_count', 'views', 'region'
    ];

    protected $casts = [
        'gallery' => 'array',
        'tags' => 'array',
        'meta_data' => 'array',
        'videos' => 'array',
        'published_at' => 'datetime',
        'featured' => 'boolean',
        'breaking' => 'boolean'
    ];

    protected $appends = ['featured_image_url', 'gallery_urls', 'read_time'];

    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function votes()
    {
        return $this->morphMany(Vote::class, 'voteable');
    }

    public function comments()
    {
        return $this->hasMany(NewsComment::class);
    }

    // Accessors
    public function getFeaturedImageUrlAttribute()
    {
        if (!$this->featured_image) {
            return asset('storage/images/news/default-news.jpg');
        }
        
        // If it's already a full URL, return as-is
        if (str_starts_with($this->featured_image, 'http://') || str_starts_with($this->featured_image, 'https://')) {
            return $this->featured_image;
        }
        
        // If it already starts with storage/, don't add it again
        if (str_starts_with($this->featured_image, 'storage/')) {
            return asset($this->featured_image);
        }
        
        // Otherwise, add storage/ prefix
        return asset('storage/' . $this->featured_image);
    }

    public function getGalleryUrlsAttribute()
    {
        if (!$this->gallery) return [];
        
        return collect($this->gallery)->map(function ($image) {
            return asset('storage/' . $image);
        })->toArray();
    }

    public function getReadTimeAttribute()
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $averageWPM = 200; // Average words per minute reading speed
        return max(1, ceil($wordCount / $averageWPM));
    }

    // Mutators
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('content', 'LIKE', "%{$term}%")
              ->orWhere('excerpt', 'LIKE', "%{$term}%");
        });
    }

    // Methods
    public function incrementViews()
    {
        $this->increment('views');
    }

    public function isPublished()
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at <= now();
    }

    public function canBeEditedBy(User $user)
    {
        return $user->hasRole('admin') || $user->id === $this->author_id;
    }
}
