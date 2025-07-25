<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    use HasFactory;

    protected $table = 'forum_threads';
    protected $fillable = ['title', 'content', 'user_id', 'category'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}