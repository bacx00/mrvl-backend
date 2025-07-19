<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;

class EventSeeder extends Seeder
{
    public function run()
    {
        Event::create([
            'name'        => 'Spring Invitational',
            'description' => 'An online invitational tournament featuring top teams.',
            'date'        => now()->addDays(10),
            'location'    => 'Online'
        ]);
        Event::create([
            'name'        => 'Summer Finals',
            'description' => 'The grand finals at the esports arena.',
            'date'        => now()->addMonths(2),
            'location'    => 'Esports Arena, Cityville'
        ]);
    }
}