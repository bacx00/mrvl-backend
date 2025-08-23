<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarvelRivalsGameMode extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'rules'
    ];

    protected $casts = [
        'rules' => 'array'
    ];

    /**
     * Get all available game modes for Marvel Rivals
     */
    public static function getGameModes()
    {
        return [
            'domination' => [
                'name' => 'Domination',
                'type' => 'control',
                'description' => 'Teams fight to control a single point. Similar to King of the Hill.',
                'rules' => [
                    'format' => 'best_of_3_rounds',
                    'objective' => 'Capture and hold the point to 100%',
                    'win_condition' => 'First to 2 round wins'
                ]
            ],
            'convoy' => [
                'name' => 'Convoy',
                'type' => 'payload',
                'description' => 'One team escorts a vehicle while the other defends. Similar to Payload.',
                'rules' => [
                    'format' => 'attack_defend',
                    'objective' => 'Push the convoy to the destination or stop it',
                    'win_condition' => 'Team that pushes furthest wins'
                ]
            ],
            'convergence' => [
                'name' => 'Convergence',
                'type' => 'hybrid',
                'description' => 'Combines Domination and Convoy. Capture a point then escort the vehicle.',
                'rules' => [
                    'format' => 'hybrid_attack_defend',
                    'objective' => 'Capture point then escort convoy',
                    'win_condition' => 'Team that progresses furthest wins'
                ]
            ]
        ];
    }

    /**
     * Get tournament format options
     */
    public static function getTournamentFormats()
    {
        return [
            'bo1' => ['value' => 1, 'label' => 'Best of 1', 'description' => 'Single game'],
            'bo3' => ['value' => 3, 'label' => 'Best of 3', 'description' => 'First to 2 wins'],
            'bo5' => ['value' => 5, 'label' => 'Best of 5', 'description' => 'First to 3 wins'],
            'bo7' => ['value' => 7, 'label' => 'Best of 7', 'description' => 'First to 4 wins'],
            'bo9' => ['value' => 9, 'label' => 'Best of 9', 'description' => 'First to 5 wins']
        ];
    }

    /**
     * Get map pool for a specific game mode
     */
    public static function getMapPool($gameMode = null)
    {
        $maps = [
            'domination' => [
                'Shin-Shibuya',
                'Spider Islands',
                'Hydra Base'
            ],
            'convoy' => [
                'Tokyo 2099: Shinjuku',
                'Klyntar',
                'Midtown Manhattan'
            ],
            'convergence' => [
                'Yggsgard: Yggdrasil Path',
                'Wakanda: Birnin T\'Challa',
                'Hell\'s Heaven'
            ]
        ];

        if ($gameMode && isset($maps[$gameMode])) {
            return $maps[$gameMode];
        }

        return $maps;
    }
}