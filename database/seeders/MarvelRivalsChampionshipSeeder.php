<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Player;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class MarvelRivalsChampionshipSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Create events first
            $events = $this->createEvents();
            
            // Create teams and players for each region
            $this->createNorthAmericaTeams($events['na']);
            $this->createEMEATeams($events['emea']);
            $this->createAsiaTeams($events['asia']);
            $this->createAmericasTeams($events['americas']);
            $this->createOceaniaTeams($events['oceania']);
        });
    }

    private function createEvents()
    {
        $events = [];
        
        // Get or create a default organizer
        $organizer = \App\Models\User::where('email', 'admin@example.com')->first();
        if (!$organizer) {
            $organizer = \App\Models\User::first();
        }
        
        // North America Invitational
        $events['na'] = Event::firstOrCreate([
            'name' => 'Marvel Rivals Invitational 2025: North America',
        ], [
            'slug' => 'marvel-rivals-invitational-2025-north-america',
            'start_date' => '2025-03-14',
            'end_date' => '2025-03-23',
            'description' => 'An online North American Marvel Rivals Showmatch organized by NetEase featuring 8 teams competing for $100,000 USD.',
            'tier' => 'A',
            'prize_pool' => 100000,
            'status' => 'completed',
            'format' => 'double_elimination',
            'region' => 'NA',
            'type' => 'invitational',
            'game_mode' => 'Marvel Rivals',
            'organizer_id' => $organizer->id,
            'max_teams' => 8
        ]);

        // EMEA Ignite Stage 1
        $events['emea'] = Event::firstOrCreate([
            'name' => 'Marvel Rivals Ignite 2025 Stage 1: EMEA',
        ], [
            'slug' => 'marvel-rivals-ignite-2025-stage-1-emea',
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'description' => 'An online European Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
            'tier' => 'A',
            'prize_pool' => 250000,
            'status' => 'completed',
            'format' => 'group_stage',
            'region' => 'EU',
            'type' => 'tournament',
            'game_mode' => 'Marvel Rivals',
            'organizer_id' => $organizer->id,
            'max_teams' => 16
        ]);

        // Asia Ignite Stage 1
        $events['asia'] = Event::firstOrCreate([
            'name' => 'Marvel Rivals Ignite 2025 Stage 1: Asia',
        ], [
            'slug' => 'marvel-rivals-ignite-2025-stage-1-asia',
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'description' => 'An online Asian Marvel Rivals tournament organized by NetEase featuring 12 teams competing for $100,000 USD.',
            'tier' => 'A',
            'prize_pool' => 100000,
            'status' => 'completed',
            'format' => 'group_stage',
            'region' => 'APAC',
            'type' => 'tournament',
            'game_mode' => 'Marvel Rivals',
            'organizer_id' => $organizer->id,
            'max_teams' => 12
        ]);

        // Americas Ignite Stage 1
        $events['americas'] = Event::firstOrCreate([
            'name' => 'Marvel Rivals Ignite 2025 Stage 1: Americas',
        ], [
            'slug' => 'marvel-rivals-ignite-2025-stage-1-americas',
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-29',
            'description' => 'An online Americas Marvel Rivals tournament organized by NetEase featuring 16 teams competing for $250,000 USD.',
            'tier' => 'A',
            'prize_pool' => 250000,
            'status' => 'completed',
            'format' => 'group_stage',
            'region' => 'NA',
            'type' => 'tournament',
            'game_mode' => 'Marvel Rivals',
            'organizer_id' => $organizer->id,
            'max_teams' => 16
        ]);

        // Oceania Ignite Stage 1
        $events['oceania'] = Event::firstOrCreate([
            'name' => 'Marvel Rivals Ignite 2025 Stage 1: Oceania',
        ], [
            'slug' => 'marvel-rivals-ignite-2025-stage-1-oceania',
            'start_date' => '2025-06-12',
            'end_date' => '2025-06-22',
            'description' => 'An online Oceanian Marvel Rivals tournament organized by NetEase featuring 8 teams competing for $75,000 USD.',
            'tier' => 'A',
            'prize_pool' => 75000,
            'status' => 'completed',
            'format' => 'group_stage',
            'region' => 'OCE',
            'type' => 'tournament',
            'game_mode' => 'Marvel Rivals',
            'organizer_id' => $organizer->id,
            'max_teams' => 8
        ]);

        return $events;
    }

    private function createNorthAmericaTeams($event)
    {
        // 100 Thieves - 1st Place
        $team100T = Team::firstOrCreate([
            'name' => '100 Thieves',
        ], [
            'short_name' => '100T',
            'region' => 'North America',
            'country' => 'United States',
            'social_links' => ['twitter' => 'https://twitter.com/100Thieves'],
            'platform' => 'PC',
            'game' => 'Marvel Rivals'
        ]);

        $event->teams()->syncWithoutDetaching([$team100T->id => [
            'placement' => 1,
            'prize_money' => 40000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // 100 Thieves Players
        $this->createPlayer('Billion', 'Flex', 'United States', $team100T->id);
        $this->createPlayer('Terra', 'Duelist', 'Canada', $team100T->id);
        $this->createPlayer('delenaa', 'Duelist', 'United States', $team100T->id);
        $this->createPlayer('Vinnie', 'Vanguard', 'United States', $team100T->id);
        $this->createPlayer('TTK', 'Vanguard', 'United States', $team100T->id);
        $this->createPlayer('SJP', 'Strategist', 'United States', $team100T->id);
        $this->createPlayer('hxrvey', 'Strategist', 'United Kingdom', $team100T->id);

        // FlyQuest - 2nd Place
        $teamFQ = Team::firstOrCreate([
            'name' => 'FlyQuest',
        ], [
            'abbreviation' => 'FQ',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'twitter' => 'https://twitter.com/FlyQuest',
            'description' => 'Marvel Rivals Invitational 2025 NA Runner-up'
        ]);

        $event->teams()->syncWithoutDetaching([$teamFQ->id => [
            'placement' => 2,
            'prize_money' => 20000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // FlyQuest Players
        $this->createPlayer('Yokie', 'Flex', 'United States', $teamFQ->id);
        $this->createPlayer('adios', 'Duelist', 'United States', $teamFQ->id);
        $this->createPlayer('lyte', 'Duelist', 'United States', $teamFQ->id);
        $this->createPlayer('energy', 'Duelist', 'United States', $teamFQ->id);
        $this->createPlayer('SparkChief', 'Vanguard', 'Mexico', $teamFQ->id);
        $this->createPlayer('Ghasklin', 'Vanguard', 'United Kingdom', $teamFQ->id);
        $this->createPlayer('coopertastic', 'Strategist', 'United States', $teamFQ->id);
        $this->createPlayer('Zelos', 'Strategist', 'Canada', $teamFQ->id);

        // Sentinels - 3rd Place
        $teamSEN = Team::firstOrCreate([
            'name' => 'Sentinels',
        ], [
            'abbreviation' => 'SEN',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'twitter' => 'https://twitter.com/Sentinels',
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamSEN->id => [
            'placement' => 3,
            'prize_money' => 12000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Sentinels Players
        $this->createPlayer('Crimzo', 'Strategist', 'Canada', $teamSEN->id);
        $this->createPlayer('Anexile', 'Flex', 'Canada', $teamSEN->id);
        $this->createPlayer('SuperGomez', 'Duelist', 'Colombia', $teamSEN->id);
        $this->createPlayer('Rymazing', 'Duelist', 'United States', $teamSEN->id);
        $this->createPlayer('Hogz', 'Vanguard', 'Canada', $teamSEN->id);
        $this->createPlayer('Coluge', 'Vanguard', 'United States', $teamSEN->id);
        $this->createPlayer('aramori', 'Strategist', 'Canada', $teamSEN->id);
        $this->createPlayer('Karova', 'Strategist', 'United States', $teamSEN->id);

        // ENVY - 4th Place
        $teamENVY = Team::firstOrCreate([
            'name' => 'ENVY',
        ], [
            'abbreviation' => 'ENVY',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamENVY->id => [
            'placement' => 4,
            'prize_money' => 8000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // ENVY Players
        $this->createPlayer('Shpeediry', 'Duelist', 'United States', $teamENVY->id);
        $this->createPlayer('cal', 'Duelist', 'Canada', $teamENVY->id);
        $this->createPlayer('nkae', 'Duelist', 'Canada', $teamENVY->id);
        $this->createPlayer('iRemiix', 'Vanguard', 'Puerto Rico', $teamENVY->id);
        $this->createPlayer('SPACE', 'Vanguard', 'United States', $teamENVY->id);
        $this->createPlayer('Paintbrush', 'Strategist', 'United States', $teamENVY->id);
        $this->createPlayer('sleepy', 'Strategist', 'United States', $teamENVY->id);

        // Shikigami - 5th-8th Place
        $teamShikigami = Team::firstOrCreate([
            'name' => 'Shikigami',
        ], [
            'abbreviation' => 'SKG',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamShikigami->id => [
            'placement' => 8,
            'prize_money' => 5000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // NTMR - 5th-8th Place
        $teamNTMR = Team::firstOrCreate([
            'name' => 'NTMR',
        ], [
            'abbreviation' => 'NTMR',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamNTMR->id => [
            'placement' => 7,
            'prize_money' => 5000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // SHROUD-X - 5th-8th Place
        $teamSHROUDX = Team::firstOrCreate([
            'name' => 'SHROUD-X',
        ], [
            'abbreviation' => 'SHROUD',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Shroud\'s Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamSHROUDX->id => [
            'placement' => 6,
            'prize_money' => 5000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Rad Esports - 5th-8th Place
        $teamRad = Team::firstOrCreate([
            'name' => 'Rad Esports',
        ], [
            'abbreviation' => 'RAD',
            'region' => 'North America',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamRad->id => [
            'placement' => 5,
            'prize_money' => 5000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Rad Esports Players
        $this->createPlayer('XEYTEX', 'Duelist', 'United States', $teamRad->id);
        $this->createPlayer('Prota', 'Strategist', 'United States', $teamRad->id);
    }

    private function createEMEATeams($event)
    {
        // Brr Brr Patapim - 1st Place
        $teamBBP = Team::firstOrCreate([
            'name' => 'Brr Brr Patapim',
        ], [
            'abbreviation' => 'BBP',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Marvel Rivals Ignite 2025 EMEA Champions'
        ]);

        $event->teams()->syncWithoutDetaching([$teamBBP->id => [
            'placement' => 1,
            'prize_money' => 70000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Brr Brr Patapim Players
        $this->createPlayer('Salah', 'Duelist', 'United Kingdom', $teamBBP->id);
        $this->createPlayer('Romanonico', 'Duelist', 'France', $teamBBP->id);
        $this->createPlayer('Tanuki', 'Duelist', 'Netherlands', $teamBBP->id);
        $this->createPlayer('Pokey', 'Duelist', 'Norway', $teamBBP->id);
        $this->createPlayer('Nzo', 'Vanguard', 'France', $teamBBP->id);
        $this->createPlayer('Polly', 'Vanguard', 'Norway', $teamBBP->id);
        $this->createPlayer('Alx', 'Strategist', 'Bulgaria', $teamBBP->id);
        $this->createPlayer('Ken', 'Strategist', 'Norway', $teamBBP->id);

        // Rad EU - 2nd Place
        $teamRadEU = Team::firstOrCreate([
            'name' => 'Rad EU',
        ], [
            'abbreviation' => 'RAD',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamRadEU->id => [
            'placement' => 2,
            'prize_money' => 35000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Rad EU Players
        $this->createPlayer('Skyza', 'Flex', 'United States', $teamRadEU->id);
        $this->createPlayer('Sestroyed', 'Duelist', 'Lithuania', $teamRadEU->id);
        $this->createPlayer('Meliø', 'Duelist', 'Denmark', $teamRadEU->id);
        $this->createPlayer('Naga', 'Duelist', 'Denmark', $teamRadEU->id);
        $this->createPlayer('Raajaro', 'Vanguard', 'Finland', $teamRadEU->id);
        $this->createPlayer('TrqstMe', 'Vanguard', 'Germany', $teamRadEU->id);
        $this->createPlayer('Lv1Crook', 'Strategist', 'Hungary', $teamRadEU->id);
        $this->createPlayer('Fate', 'Strategist', 'United Kingdom', $teamRadEU->id);

        // Virtus.pro - 3rd Place
        $teamVP = Team::firstOrCreate([
            'name' => 'Virtus.pro',
        ], [
            'abbreviation' => 'VP',
            'region' => 'Europe',
            'country' => 'Russia',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'twitter' => 'https://twitter.com/virtuspro',
            'description' => 'Professional esports organization'
        ]);

        $event->teams()->syncWithoutDetaching([$teamVP->id => [
            'placement' => 3,
            'prize_money' => 25000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Zero Tenacity - 4th Place
        $teamZT = Team::firstOrCreate([
            'name' => 'Zero Tenacity',
        ], [
            'abbreviation' => 'ZT',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamZT->id => [
            'placement' => 4,
            'prize_money' => 20000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Zero Tenacity Players
        $this->createPlayer('SmashNezz', 'Duelist', 'Denmark', $teamZT->id);
        $this->createPlayer('Knuten', 'Duelist', 'Denmark', $teamZT->id);
        $this->createPlayer('ducky1', 'Vanguard', 'United Kingdom', $teamZT->id);
        $this->createPlayer('Lugia', 'Vanguard', 'United Kingdom', $teamZT->id);
        $this->createPlayer('Wyni', 'Strategist', 'Spain', $teamZT->id);
        $this->createPlayer('Oasis', 'Strategist', 'Sweden', $teamZT->id);

        // Team Peps - 5th-6th Place
        $teamPeps = Team::firstOrCreate([
            'name' => 'Team Peps',
        ], [
            'abbreviation' => 'PEPS',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamPeps->id => [
            'placement' => 5,
            'prize_money' => 15000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // L9 - 5th-6th Place
        $teamL9 = Team::firstOrCreate([
            'name' => 'L9',
        ], [
            'abbreviation' => 'L9',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamL9->id => [
            'placement' => 6,
            'prize_money' => 15000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // All Business - 7th-8th Place
        $teamAB = Team::firstOrCreate([
            'name' => 'All Business',
        ], [
            'abbreviation' => 'AB',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamAB->id => [
            'placement' => 7,
            'prize_money' => 10000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Insomnia - 7th-8th Place
        $teamInsomnia = Team::firstOrCreate([
            'name' => 'Insomnia',
        ], [
            'abbreviation' => 'INSM',
            'region' => 'Europe',
            'country' => 'Europe',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamInsomnia->id => [
            'placement' => 8,
            'prize_money' => 10000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Create remaining EMEA teams (9-16th place)
        $this->createRemainingEMEATeams($event);
    }

    private function createRemainingEMEATeams($event)
    {
        $teams = [
            ['name' => 'Schmungus', 'abbreviation' => 'SCH', 'placement' => 9, 'prize' => 7500],
            ['name' => 'Yoinkanda', 'abbreviation' => 'YOINK', 'placement' => 10, 'prize' => 7500],
            ['name' => 'Al Qadsiah', 'abbreviation' => 'AQ', 'placement' => 11, 'prize' => 7500],
            ['name' => 'OG Seed', 'abbreviation' => 'OGS', 'placement' => 12, 'prize' => 7500],
            ['name' => 'BloodKariudo', 'abbreviation' => 'BK', 'placement' => 13, 'prize' => 5000],
            ['name' => 'DUSTY', 'abbreviation' => 'DUSTY', 'placement' => 14, 'prize' => 5000],
            ['name' => 'FYR Strays', 'abbreviation' => 'FYR', 'placement' => 15, 'prize' => 5000],
            ['name' => 'ZERO.PERCENT', 'abbreviation' => 'ZERO', 'placement' => 16, 'prize' => 5000],
        ];

        foreach ($teams as $teamData) {
            $team = Team::firstOrCreate([
                'name' => $teamData['name'],
            ], [
                'abbreviation' => $teamData['abbreviation'],
                'region' => 'Europe',
                'country' => 'Europe',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Professional Marvel Rivals team'
            ]);

            $event->teams()->syncWithoutDetaching([$team->id => [
                'placement' => $teamData['placement'],
                'prize_money' => $teamData['prize'],
                'status' => 'confirmed',
                'registered_at' => now()
            ]]);
        }
    }

    private function createAsiaTeams($event)
    {
        // REJECT - 1st Place
        $teamREJECT = Team::firstOrCreate([
            'name' => 'REJECT',
        ], [
            'abbreviation' => 'RC',
            'region' => 'Asia',
            'country' => 'South Korea',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Marvel Rivals Ignite 2025 Asia Champions'
        ]);

        $event->teams()->syncWithoutDetaching([$teamREJECT->id => [
            'placement' => 1,
            'prize_money' => 32000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // REJECT Players
        $this->createPlayer('finale', 'Duelist', 'South Korea', $teamREJECT->id);
        $this->createPlayer('GARGOYLE', 'Duelist', 'South Korea', $teamREJECT->id);
        $this->createPlayer('piggy', 'Vanguard', 'South Korea', $teamREJECT->id);
        $this->createPlayer('Gnome', 'Vanguard', 'South Korea', $teamREJECT->id);
        $this->createPlayer('MOKA', 'Strategist', 'South Korea', $teamREJECT->id);
        $this->createPlayer('DDobi', 'Strategist', 'South Korea', $teamREJECT->id);

        // Gen.G Esports - 2nd Place
        $teamGenG = Team::firstOrCreate([
            'name' => 'Gen.G Esports',
        ], [
            'abbreviation' => 'GEN',
            'region' => 'Asia',
            'country' => 'South Korea',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'twitter' => 'https://twitter.com/GenG',
            'description' => 'Professional esports organization'
        ]);

        $event->teams()->syncWithoutDetaching([$teamGenG->id => [
            'placement' => 2,
            'prize_money' => 16000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Gen.G Players
        $this->createPlayer('Xzi', 'Duelist', 'South Korea', $teamGenG->id);
        $this->createPlayer('Brownie', 'Duelist', 'South Korea', $teamGenG->id);
        $this->createPlayer('KAIDIA', 'Duelist', 'South Korea', $teamGenG->id);
        $this->createPlayer('CHOPPA', 'Vanguard', 'South Korea', $teamGenG->id);
        $this->createPlayer('FUNFUN', 'Vanguard', 'South Korea', $teamGenG->id);
        $this->createPlayer('Dotori', 'Strategist', 'South Korea', $teamGenG->id);
        $this->createPlayer('SNAKE', 'Strategist', 'South Korea', $teamGenG->id);

        // Crazy Raccoon - 3rd Place
        $teamCR = Team::firstOrCreate([
            'name' => 'Crazy Raccoon',
        ], [
            'abbreviation' => 'CR',
            'region' => 'Asia',
            'country' => 'Japan',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'twitter' => 'https://twitter.com/crazyraccoon406',
            'description' => 'Professional esports organization'
        ]);

        $event->teams()->syncWithoutDetaching([$teamCR->id => [
            'placement' => 3,
            'prize_money' => 12000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Crazy Raccoon Players
        $this->createPlayer('VITAL', 'Duelist', 'South Korea', $teamCR->id);
        $this->createPlayer('Hayan', 'Duelist', 'South Korea', $teamCR->id);
        $this->createPlayer('RIPASUKO', 'Vanguard', 'Japan', $teamCR->id);
        $this->createPlayer('JT3', 'Vanguard', 'Japan', $teamCR->id);
        $this->createPlayer('SeungHoon', 'Strategist', 'South Korea', $teamCR->id);
        $this->createPlayer('Rebirth', 'Strategist', 'South Korea', $teamCR->id);

        // XOXO01 - 4th Place
        $teamXOXO = Team::firstOrCreate([
            'name' => 'XOXO01',
        ], [
            'abbreviation' => 'XOXO',
            'region' => 'Asia',
            'country' => 'Taiwan',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamXOXO->id => [
            'placement' => 4,
            'prize_money' => 10000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // XOXO01 Players
        $this->createPlayer('Bobok1ng', 'Duelist', 'Taiwan', $teamXOXO->id);
        $this->createPlayer('Hope', 'Duelist', 'China', $teamXOXO->id);
        $this->createPlayer('Errmo', 'Vanguard', 'Taiwan', $teamXOXO->id);
        $this->createPlayer('MaoLi', 'Vanguard', 'China', $teamXOXO->id);
        $this->createPlayer('CASSIUS', 'Strategist', 'Taiwan', $teamXOXO->id);
        $this->createPlayer('CQB', 'Strategist', 'China', $teamXOXO->id);

        // O2 Blast - 5th-6th Place
        $teamO2 = Team::firstOrCreate([
            'name' => 'O2 Blast',
        ], [
            'abbreviation' => 'O2B',
            'region' => 'Asia',
            'country' => 'South Korea',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamO2->id => [
            'placement' => 5,
            'prize_money' => 6000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // O2 Blast Players
        $this->createPlayer('re yi', 'Duelist', 'South Korea', $teamO2->id);
        $this->createPlayer('Roco', 'Duelist', 'South Korea', $teamO2->id);
        $this->createPlayer('Onse', 'Vanguard', 'South Korea', $teamO2->id);
        $this->createPlayer('Welsh Corgi', 'Vanguard', 'South Korea', $teamO2->id);
        $this->createPlayer('Felix', 'Strategist', 'South Korea', $teamO2->id);
        $this->createPlayer('Solmin', 'Strategist', 'South Korea', $teamO2->id);

        // AssembleFire - 5th-6th Place
        $teamAF = Team::firstOrCreate([
            'name' => 'AssembleFire',
        ], [
            'abbreviation' => 'AF',
            'region' => 'Asia',
            'country' => 'Thailand',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamAF->id => [
            'placement' => 6,
            'prize_money' => 6000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // AssembleFire Players
        $this->createPlayer('KingdomGod', 'Duelist', 'Thailand', $teamAF->id);
        $this->createPlayer('SlowestSoldier', 'Duelist', 'Thailand', $teamAF->id);
        $this->createPlayer('ZEROONE', 'Vanguard', 'Thailand', $teamAF->id);
        $this->createPlayer('หมาเฟีย', 'Vanguard', 'Thailand', $teamAF->id);
        $this->createPlayer('Xenoz', 'Strategist', 'Thailand', $teamAF->id);
        $this->createPlayer('ชบาเเก้ว', 'Strategist', 'Thailand', $teamAF->id);

        // Create remaining Asia teams
        $this->createRemainingAsiaTeams($event);
    }

    private function createRemainingAsiaTeams($event)
    {
        $teams = [
            ['name' => 'AlenTiar', 'abbreviation' => 'ALT', 'placement' => 7, 'prize' => 4000, 'country' => 'Thailand'],
            ['name' => 'MVNEsport', 'abbreviation' => 'MVN', 'placement' => 8, 'prize' => 4000, 'country' => 'Vietnam'],
            ['name' => 'Onyx Esports', 'abbreviation' => 'ONYX', 'placement' => 9, 'prize' => 2000, 'country' => 'Singapore'],
            ['name' => 'VARREL', 'abbreviation' => 'VRL', 'placement' => 10, 'prize' => 2000, 'country' => 'Japan'],
            ['name' => 'ALPHA PLUS', 'abbreviation' => 'AP', 'placement' => 11, 'prize' => 2000, 'country' => 'Vietnam'],
            ['name' => 'SCARZ', 'abbreviation' => 'SZ', 'placement' => 12, 'prize' => 2000, 'country' => 'Japan'],
        ];

        foreach ($teams as $teamData) {
            $team = Team::firstOrCreate([
                'name' => $teamData['name'],
            ], [
                'abbreviation' => $teamData['abbreviation'],
                'region' => 'Asia',
                'country' => $teamData['country'],
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Professional Marvel Rivals team'
            ]);

            $event->teams()->syncWithoutDetaching([$team->id => [
                'placement' => $teamData['placement'],
                'prize_money' => $teamData['prize'],
                'status' => 'confirmed',
                'registered_at' => now()
            ]]);
        }

        // Add AlenTiar players
        $teamALT = Team::where('name', 'AlenTiar')->first();
        if ($teamALT) {
            $this->createPlayer('N1nym', 'Duelist', 'Thailand', $teamALT->id);
            $this->createPlayer('RealJeff OTP', 'Duelist', 'Thailand', $teamALT->id);
            $this->createPlayer('Cartiace', 'Vanguard', 'Thailand', $teamALT->id);
            $this->createPlayer('MAXKEN', 'Vanguard', 'Thailand', $teamALT->id);
            $this->createPlayer('THE Deep', 'Strategist', 'Thailand', $teamALT->id);
            $this->createPlayer('Midstar', 'Strategist', 'Thailand', $teamALT->id);
        }
    }

    private function createAmericasTeams($event)
    {
        // Sentinels - 1st Place (Americas)
        $teamSEN2 = Team::where('name', 'Sentinels')->first();
        if (!$teamSEN2) {
            $teamSEN2 = Team::create([
                'name' => 'Sentinels',
                'abbreviation' => 'SEN',
                'region' => 'Americas',
                'country' => 'United States',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'twitter' => 'https://twitter.com/Sentinels',
                'description' => 'Marvel Rivals Ignite 2025 Americas Champions'
            ]);
        }

        $event->teams()->syncWithoutDetaching([$teamSEN2->id => [
            'placement' => 1,
            'prize_money' => 70000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // 100 Thieves - 2nd Place (Americas)
        $team100T2 = Team::where('name', '100 Thieves')->first();
        if (!$team100T2) {
            $team100T2 = Team::create([
                'name' => '100 Thieves',
                'abbreviation' => '100T',
                'region' => 'Americas',
                'country' => 'United States',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'twitter' => 'https://twitter.com/100Thieves',
                'description' => 'Professional esports organization'
            ]);
        }

        $event->teams()->syncWithoutDetaching([$team100T2->id => [
            'placement' => 2,
            'prize_money' => 35000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // ENVY - 3rd Place (Americas)
        $teamENVY2 = Team::where('name', 'ENVY')->first();
        if (!$teamENVY2) {
            $teamENVY2 = Team::create([
                'name' => 'ENVY',
                'abbreviation' => 'ENVY',
                'region' => 'Americas',
                'country' => 'United States',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Professional Marvel Rivals team'
            ]);
        }

        $event->teams()->syncWithoutDetaching([$teamENVY2->id => [
            'placement' => 3,
            'prize_money' => 25000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // SHROUD-X - 4th Place (Americas)
        $teamSHROUDX2 = Team::where('name', 'SHROUD-X')->first();
        if (!$teamSHROUDX2) {
            $teamSHROUDX2 = Team::create([
                'name' => 'SHROUD-X',
                'abbreviation' => 'SHROUD',
                'region' => 'Americas',
                'country' => 'United States',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Shroud\'s Marvel Rivals team'
            ]);
        }

        $event->teams()->syncWithoutDetaching([$teamSHROUDX2->id => [
            'placement' => 4,
            'prize_money' => 20000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // SHROUD-X Players (Americas roster)
        $this->createPlayer('Vision', 'Duelist', 'United States', $teamSHROUDX2->id);
        $this->createPlayer('doomed', 'Duelist', 'United States', $teamSHROUDX2->id);
        $this->createPlayer('Impuniti', 'Vanguard', 'United States', $teamSHROUDX2->id);
        $this->createPlayer('dongmin', 'Vanguard', 'United States', $teamSHROUDX2->id);
        $this->createPlayer('Fidel', 'Strategist', 'United States', $teamSHROUDX2->id);
        $this->createPlayer('Nuk', 'Strategist', 'United States', $teamSHROUDX2->id);

        // Ego Death - 5th-6th Place
        $teamEgo = Team::firstOrCreate([
            'name' => 'Ego Death',
        ], [
            'abbreviation' => 'EGO',
            'region' => 'Americas',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamEgo->id => [
            'placement' => 5,
            'prize_money' => 15000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Ego Death Players
        $this->createPlayer('Self', 'Duelist', 'United States', $teamEgo->id);
        $this->createPlayer('XEYTEX', 'Duelist', 'United States', $teamEgo->id);
        $this->createPlayer('Somble', 'Vanguard', 'United States', $teamEgo->id);
        $this->createPlayer('soko', 'Vanguard', 'United States', $teamEgo->id);
        $this->createPlayer('far', 'Strategist', 'United States', $teamEgo->id);
        $this->createPlayer('Momentum', 'Strategist', 'United States', $teamEgo->id);

        // tekixd - 5th-6th Place
        $teamTekixd = Team::firstOrCreate([
            'name' => 'tekixd',
        ], [
            'abbreviation' => 'TKD',
            'region' => 'Americas',
            'country' => 'United States',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamTekixd->id => [
            'placement' => 6,
            'prize_money' => 15000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // tekixd Players
        $this->createPlayer('Avery', 'Duelist', 'Canada', $teamTekixd->id);
        $this->createPlayer('TAP', 'Duelist', 'Netherlands', $teamTekixd->id);
        $this->createPlayer('blur', 'Vanguard', 'Wales', $teamTekixd->id);
        $this->createPlayer('Brute', 'Vanguard', 'United Kingdom', $teamTekixd->id);
        $this->createPlayer('Woofles', 'Strategist', 'United States', $teamTekixd->id);
        $this->createPlayer('aad', 'Strategist', 'United States', $teamTekixd->id);

        // Create remaining Americas teams
        $this->createRemainingAmericasTeams($event);
    }

    private function createRemainingAmericasTeams($event)
    {
        $teams = [
            ['name' => 'FlyQuest RED', 'abbreviation' => 'FQR', 'placement' => 7, 'prize' => 10000],
            ['name' => 'Legends', 'abbreviation' => 'LGD', 'placement' => 8, 'prize' => 10000],
            ['name' => 'NRG', 'abbreviation' => 'NRG', 'placement' => 9, 'prize' => 7500],
            ['name' => 'Cloud9', 'abbreviation' => 'C9', 'placement' => 10, 'prize' => 7500],
            ['name' => 'Evil Geniuses', 'abbreviation' => 'EG', 'placement' => 11, 'prize' => 7500],
            ['name' => 'Version1', 'abbreviation' => 'V1', 'placement' => 12, 'prize' => 7500],
            ['name' => 'Luminosity Gaming', 'abbreviation' => 'LG', 'placement' => 13, 'prize' => 5000],
            ['name' => 'TSM', 'abbreviation' => 'TSM', 'placement' => 14, 'prize' => 5000],
            ['name' => 'FURIA', 'abbreviation' => 'FURIA', 'placement' => 15, 'prize' => 5000],
            ['name' => 'LOUD', 'abbreviation' => 'LOUD', 'placement' => 16, 'prize' => 5000],
        ];

        foreach ($teams as $teamData) {
            $team = Team::firstOrCreate([
                'name' => $teamData['name'],
            ], [
                'abbreviation' => $teamData['abbreviation'],
                'region' => 'Americas',
                'country' => 'United States',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Professional Marvel Rivals team'
            ]);

            $event->teams()->syncWithoutDetaching([$team->id => [
                'placement' => $teamData['placement'],
                'prize_money' => $teamData['prize'],
                'status' => 'confirmed',
                'registered_at' => now()
            ]]);
        }
    }

    private function createOceaniaTeams($event)
    {
        // Ground Zero Gaming - 1st Place
        $teamGZG = Team::firstOrCreate([
            'name' => 'Ground Zero Gaming',
        ], [
            'abbreviation' => 'GZG',
            'region' => 'Oceania',
            'country' => 'Australia',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Marvel Rivals Ignite 2025 Oceania Champions'
        ]);

        $event->teams()->syncWithoutDetaching([$teamGZG->id => [
            'placement' => 1,
            'prize_money' => 30000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Ground Zero Gaming Players
        $this->createPlayer('FMCL', 'Duelist', 'New Zealand', $teamGZG->id);
        $this->createPlayer('SIX', 'Duelist', 'Botswana', $teamGZG->id);
        $this->createPlayer('duep', 'Vanguard', 'Australia', $teamGZG->id);
        $this->createPlayer('Zenstarry', 'Vanguard', 'Australia', $teamGZG->id);
        $this->createPlayer('KINGBOB7', 'Strategist', 'Australia', $teamGZG->id);
        $this->createPlayer('Mattyaf', 'Strategist', 'France', $teamGZG->id);

        // The Vicious - 2nd Place
        $teamVicious = Team::firstOrCreate([
            'name' => 'The Vicious',
        ], [
            'abbreviation' => 'VIC',
            'region' => 'Oceania',
            'country' => 'Australia',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamVicious->id => [
            'placement' => 2,
            'prize_money' => 15000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // The Vicious Players
        $this->createPlayer('Revzi', 'Duelist', 'New Zealand', $teamVicious->id);
        $this->createPlayer('rib', 'Duelist', 'Bangladesh', $teamVicious->id);
        $this->createPlayer('Adam', 'Vanguard', 'Australia', $teamVicious->id);
        $this->createPlayer('lumi', 'Vanguard', 'Singapore', $teamVicious->id);
        $this->createPlayer('atlas', 'Strategist', 'Australia', $teamVicious->id);
        $this->createPlayer('asher', 'Strategist', 'Australia', $teamVicious->id);

        // Kanga Esports - 3rd Place
        $teamKanga = Team::firstOrCreate([
            'name' => 'Kanga Esports',
        ], [
            'abbreviation' => 'KNG',
            'region' => 'Oceania',
            'country' => 'Australia',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamKanga->id => [
            'placement' => 3,
            'prize_money' => 9000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Kanga Esports Players
        $this->createPlayer('Daxu', 'Duelist', 'Australia', $teamKanga->id);
        $this->createPlayer('Kronicx', 'Duelist', 'Australia', $teamKanga->id);
        $this->createPlayer('Donald', 'Vanguard', 'Australia', $teamKanga->id);
        $this->createPlayer('Tekzy', 'Vanguard', 'Australia', $teamKanga->id);
        $this->createPlayer('furikakae', 'Strategist', 'Singapore', $teamKanga->id);
        $this->createPlayer('SkittlesOCE', 'Strategist', 'Australia', $teamKanga->id);

        // Bethany - 4th Place
        $teamBethany = Team::firstOrCreate([
            'name' => 'Bethany',
        ], [
            'abbreviation' => 'BTH',
            'region' => 'Oceania',
            'country' => 'Australia',
            'status' => 'active',
            'wins' => 0,
            'losses' => 0,
            'description' => 'Professional Marvel Rivals team'
        ]);

        $event->teams()->syncWithoutDetaching([$teamBethany->id => [
            'placement' => 4,
            'prize_money' => 6000,
            'status' => 'confirmed',
            'registered_at' => now()
        ]]);

        // Bethany Players
        $this->createPlayer('azii', 'Duelist', 'Australia', $teamBethany->id);
        $this->createPlayer('leam', 'Duelist', 'Australia', $teamBethany->id);
        $this->createPlayer('Jag', 'Vanguard', 'Australia', $teamBethany->id);
        $this->createPlayer('soupie7', 'Vanguard', 'Australia', $teamBethany->id);
        $this->createPlayer('bubblecuh', 'Strategist', 'Australia', $teamBethany->id);
        $this->createPlayer('oinkk', 'Strategist', 'Australia', $teamBethany->id);

        // Create remaining Oceania teams
        $teams = [
            ['name' => 'Quetzal', 'abbreviation' => 'QTZ', 'placement' => 5, 'prize' => 4500],
            ['name' => 'Zavier Hope', 'abbreviation' => 'ZH', 'placement' => 6, 'prize' => 4500],
            ['name' => 'Pig Team', 'abbreviation' => 'PIG', 'placement' => 7, 'prize' => 3000],
            ['name' => 'Gappped', 'abbreviation' => 'GAP', 'placement' => 8, 'prize' => 3000],
        ];

        foreach ($teams as $teamData) {
            $team = Team::firstOrCreate([
                'name' => $teamData['name'],
            ], [
                'abbreviation' => $teamData['abbreviation'],
                'region' => 'Oceania',
                'country' => 'Australia',
                'status' => 'active',
                'wins' => 0,
                'losses' => 0,
                'description' => 'Professional Marvel Rivals team'
            ]);

            $event->teams()->syncWithoutDetaching([$team->id => [
                'placement' => $teamData['placement'],
                'prize_money' => $teamData['prize'],
                'status' => 'confirmed',
                'registered_at' => now()
            ]]);
        }
    }

    private function createTeam($name, $shortName, $region, $country, $twitter = null)
    {
        return Team::firstOrCreate([
            'name' => $name,
        ], [
            'short_name' => $shortName,
            'region' => $region,
            'country' => $country,
            'social_links' => $twitter ? ['twitter' => $twitter] : [],
            'platform' => 'PC',
            'game' => 'Marvel Rivals'
        ]);
    }
    
    private function createPlayer($name, $role, $country, $teamId)
    {
        // Map roles to default heroes
        $defaultHeroes = [
            'Duelist' => 'Spider-Man',
            'Vanguard' => 'Hulk',
            'Strategist' => 'Mantis',
            'Tank' => 'Hulk',
            'Support' => 'Mantis',
            'DPS' => 'Spider-Man',
            'Flex' => 'Iron Man'
        ];
        
        // Map countries to regions
        $regionMap = [
            'United States' => 'NA',
            'Canada' => 'NA',
            'Mexico' => 'NA',
            'Puerto Rico' => 'NA',
            'Colombia' => 'SA',
            'United Kingdom' => 'EU',
            'France' => 'EU',
            'Germany' => 'EU',
            'Spain' => 'EU',
            'Netherlands' => 'EU',
            'Denmark' => 'EU',
            'Norway' => 'EU',
            'Sweden' => 'EU',
            'Finland' => 'EU',
            'Bulgaria' => 'EU',
            'Lithuania' => 'EU',
            'Hungary' => 'EU',
            'Russia' => 'EU',
            'Wales' => 'EU',
            'South Korea' => 'APAC',
            'Japan' => 'APAC',
            'China' => 'CN',
            'Taiwan' => 'APAC',
            'Thailand' => 'APAC',
            'Vietnam' => 'APAC',
            'Singapore' => 'APAC',
            'Australia' => 'OCE',
            'New Zealand' => 'OCE',
            'Bangladesh' => 'APAC',
            'Botswana' => 'MENA'
        ];
        
        return Player::firstOrCreate([
            'name' => $name,
            'team_id' => $teamId
        ], [
            'username' => strtolower(str_replace(' ', '', $name)),
            'role' => $role,
            'country' => $country,
            'region' => $regionMap[$country] ?? 'INTL',
            'main_hero' => $defaultHeroes[$role] ?? 'Spider-Man',
            'social_media' => [],
            'earnings' => 0
        ]);
    }
}