<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarvelRivalsTournamentSeeder extends Seeder
{
    public function run()
    {
        $events = [
            [
                'name' => 'Marvel Rivals Championship 2025 - Americas',
                'slug' => 'mrc-2025-americas',
                'description' => 'The premier Marvel Rivals tournament for the Americas region featuring 128 teams competing in a double-elimination format with Bo3 matches leading to a Bo7 grand final. Top teams advance to Marvel Rivals Ignite 2025.',
                'logo' => '/images/events/mrc-americas-2025.png',
                'banner' => '/images/events/mrc-americas-banner.png',
                'type' => 'championship',
                'tier' => 'S',
                'tournament_format' => 'marvel_rivals_championship',
                'format' => 'bo3',
                'region' => 'Americas',
                'game_mode' => '6v6 Convoy',
                'status' => 'upcoming',
                'start_date' => '2025-08-15 18:00:00',
                'end_date' => '2025-08-25 23:00:00',
                'registration_start' => '2025-07-20 12:00:00',
                'registration_end' => '2025-08-10 23:59:59',
                'timezone' => 'America/New_York',
                'max_teams' => 128,
                'prize_pool' => 500000.00,
                'currency' => 'USD',
                'prize_distribution' => json_encode([
                    'first' => 200000,
                    'second' => 100000,
                    'third' => 75000,
                    'fourth' => 50000,
                    'top_8' => 25000,
                    'top_16' => 12500
                ]),
                'organizer_id' => 1,
                'featured' => true,
                'public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Marvel Rivals Championship 2025 - EMEA',
                'slug' => 'mrc-2025-emea',
                'description' => 'European, Middle Eastern, and African championship featuring top teams competing for qualification to Marvel Rivals Ignite 2025. Double elimination bracket with Bo7 grand finals.',
                'logo' => '/images/events/mrc-emea-2025.png',
                'banner' => '/images/events/mrc-emea-banner.png',
                'type' => 'championship',
                'tier' => 'S',
                'tournament_format' => 'marvel_rivals_championship',
                'format' => 'bo3',
                'region' => 'EMEA',
                'game_mode' => '6v6 Convoy',
                'status' => 'upcoming',
                'start_date' => '2025-08-22 16:00:00',
                'end_date' => '2025-09-01 22:00:00',
                'registration_start' => '2025-07-25 10:00:00',
                'registration_end' => '2025-08-15 23:59:59',
                'timezone' => 'Europe/Berlin',
                'max_teams' => 128,
                'prize_pool' => 500000.00,
                'currency' => 'USD',
                'prize_distribution' => json_encode([
                    'first' => 200000,
                    'second' => 100000,
                    'third' => 75000,
                    'fourth' => 50000,
                    'top_8' => 25000,
                    'top_16' => 12500
                ]),
                'organizer_id' => 1,
                'featured' => true,
                'public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Marvel Rivals Ignite 2025 - Stage 1',
                'slug' => 'mri-2025-stage-1',
                'description' => 'The biggest Marvel Rivals tournament of 2025 featuring teams from all regions. $3M total prize pool with $1M for grand finals. Stage 1 features group stage followed by double elimination playoffs.',
                'logo' => '/images/events/mri-2025-stage1.png',
                'banner' => '/images/events/mri-2025-banner.png',
                'type' => 'international',
                'tier' => 'S',
                'tournament_format' => 'marvel_rivals_ignite',
                'format' => 'bo5',
                'region' => 'Global',
                'game_mode' => '6v6 Convoy',
                'status' => 'upcoming',
                'start_date' => '2025-10-15 12:00:00',
                'end_date' => '2025-11-15 20:00:00',
                'registration_start' => '2025-09-01 12:00:00',
                'registration_end' => '2025-10-01 23:59:59',
                'timezone' => 'UTC',
                'max_teams' => 64,
                'prize_pool' => 3000000.00,
                'currency' => 'USD',
                'prize_distribution' => json_encode([
                    'first' => 1000000,
                    'second' => 500000,
                    'third' => 300000,
                    'fourth' => 200000,
                    'semifinalists' => 100000,
                    'quarterfinals' => 50000
                ]),
                'organizer_id' => 1,
                'featured' => true,
                'public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Marvel Rivals Invitational - Pro Scrimmage',
                'slug' => 'mri-pro-scrimmage',
                'description' => 'Invite-only tournament featuring top 16 Marvel Rivals teams. Quick format with Bo3 matches and Bo5 finals. Perfect for testing strategies and showcasing hero mastery.',
                'logo' => '/images/events/mri-scrimmage.png',
                'banner' => '/images/events/mri-scrimmage-banner.png',
                'type' => 'invitational',
                'tier' => 'A',
                'tournament_format' => 'marvel_rivals_invitational',
                'format' => 'bo3',
                'region' => 'Global',
                'game_mode' => '6v6 Convoy',
                'status' => 'ongoing',
                'start_date' => '2025-07-15 14:00:00',
                'end_date' => '2025-07-17 22:00:00',
                'registration_start' => '2025-07-01 12:00:00',
                'registration_end' => '2025-07-10 23:59:59',
                'timezone' => 'UTC',
                'max_teams' => 16,
                'prize_pool' => 50000.00,
                'currency' => 'USD',
                'prize_distribution' => json_encode([
                    'first' => 25000,
                    'second' => 15000,
                    'third' => 7500,
                    'fourth' => 2500
                ]),
                'organizer_id' => 1,
                'featured' => true,
                'public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Marvel Rivals Community Cup - PC',
                'slug' => 'mrc-community-pc',
                'description' => 'Open community tournament for PC players. Single elimination format with 64 teams. Great opportunity for amateur teams to compete and gain experience.',
                'logo' => '/images/events/mrc-community-pc.png',
                'banner' => '/images/events/mrc-community-banner.png',
                'type' => 'community',
                'tier' => 'B',
                'tournament_format' => 'third_party_tournament',
                'format' => 'bo3',
                'region' => 'Global',
                'game_mode' => '6v6 Convoy',
                'status' => 'upcoming',
                'start_date' => '2025-08-01 16:00:00',
                'end_date' => '2025-08-03 20:00:00',
                'registration_start' => '2025-07-20 12:00:00',
                'registration_end' => '2025-07-30 23:59:59',
                'timezone' => 'UTC',
                'max_teams' => 64,
                'prize_pool' => 10000.00,
                'currency' => 'USD',
                'prize_distribution' => json_encode([
                    'first' => 5000,
                    'second' => 3000,
                    'third' => 1500,
                    'fourth' => 500
                ]),
                'organizer_id' => 1,
                'featured' => false,
                'public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Clear existing events
        DB::table('events')->truncate();
        
        // Insert Marvel Rivals tournaments
        foreach ($events as $event) {
            DB::table('events')->insert($event);
        }
        
        $this->command->info('Marvel Rivals tournaments seeded successfully!');
    }
}