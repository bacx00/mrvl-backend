<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'username', 'real_name', 'team_id', 'role', 'main_hero', 'alt_heroes',
        'region', 'country', 'rank', 'rating', 'age', 'earnings',
        'social_media', 'biography'
    ];

    protected $casts = [
        'rating' => 'float',
        'age' => 'integer',
        'alt_heroes' => 'array',
        'social_media' => 'array'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function matches()
    {
        return $this->belongsToMany(Match::class, 'match_player')
                   ->withPivot(['kills', 'deaths', 'assists', 'damage', 'healing']);
    }
}
