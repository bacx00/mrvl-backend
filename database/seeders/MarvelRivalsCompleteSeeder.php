<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarvelRivalsCompleteSeeder extends Seeder
{
    public function run()
    {
        // 1. FORUM THREADS - FIX THE 404 ERROR
        $this->seedForumThreads();
        
        // 2. MARVEL RIVALS HEROES DATA
        $this->seedMarvelHeroes();
        
        // 3. LIVE MATCH DATA
        $this->seedLiveMatches();
        
        // 4. NEWS ARTICLES
        $this->seedNewsArticles();
        
        // 5. MATCH COMMENTS
        $this->seedMatchComments();
        
        // 6. TOURNAMENTS & EVENTS
        $this->seedTournaments();
    }
    
    private function seedForumThreads()
    {
        $threads = [
            [
                'id' => 1,
                'title' => 'Welcome to Marvel Rivals Esports!',
                'content' => 'Welcome to the official Marvel Rivals esports community! Discuss strategies, team compositions, and tournament updates here.',
                'category' => 'announcements',
                'views' => 245,
                'replies' => 18,
                'pinned' => true,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(30),
                'updated_at' => Carbon::now()->subDays(30)
            ],
            [
                'id' => 2,
                'title' => 'Best Tank Compositions for Ranked',
                'content' => 'Let\'s discuss the most effective tank compositions in the current meta. Hulk + Magneto seems to be dominating.',
                'category' => 'strategies',
                'views' => 389,
                'replies' => 42,
                'pinned' => false,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(25),
                'updated_at' => Carbon::now()->subDays(2)
            ],
            [
                'id' => 15,
                'title' => 'Marvel Rivals World Championship 2025',
                'content' => 'The biggest Marvel Rivals tournament is coming! Prize pool: $500,000. Registration opens next week.',
                'category' => 'tournaments',
                'views' => 1250,
                'replies' => 89,
                'pinned' => true,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(10),
                'updated_at' => Carbon::now()->subDays(1)
            ],
            [
                'id' => 16,
                'title' => 'Support Hero Tier List - December 2024',
                'content' => 'Updated tier list for support heroes based on recent patch changes. Luna Snow still S-tier!',
                'category' => 'strategies',
                'views' => 456,
                'replies' => 23,
                'pinned' => false,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(1)
            ]
        ];
        
        foreach ($threads as $thread) {
            DB::table('forum_threads')->updateOrInsert(['id' => $thread['id']], $thread);
        }
    }
    
    private function seedMarvelHeroes()
    {
        $heroes = [
            // Duelists
            ['name' => 'Iron Man', 'role' => 'Duelist', 'abilities' => 'Repulsor Rays, Unibeam, Flight'],
            ['name' => 'Spider-Man', 'role' => 'Duelist', 'abilities' => 'Web Swing, Spider Sense, Wall Crawl'],
            ['name' => 'Black Widow', 'role' => 'Duelist', 'abilities' => 'Widow\'s Bite, Stealth, Grappling Hook'],
            ['name' => 'Hawkeye', 'role' => 'Duelist', 'abilities' => 'Precision Shot, Explosive Arrow, Hunter\'s Mark'],
            ['name' => 'Star-Lord', 'role' => 'Duelist', 'abilities' => 'Element Blasters, Jet Boot Dash, Music Mix'],
            ['name' => 'Punisher', 'role' => 'Duelist', 'abilities' => 'Heavy Weapons, Turret, Smoke Grenade'],
            ['name' => 'Winter Soldier', 'role' => 'Duelist', 'abilities' => 'Bionic Arm, Tactical Arsenal, Stealth'],
            ['name' => 'Squirrel Girl', 'role' => 'Duelist', 'abilities' => 'Squirrel Swarm, Acrobatics, Nutty Tactics'],
            
            // Tanks
            ['name' => 'Hulk', 'role' => 'Tank', 'abilities' => 'Gamma Slam, Rage Mode, Thunderclap'],
            ['name' => 'Thor', 'role' => 'Tank', 'abilities' => 'Mjolnir Throw, Lightning Strike, God Blast'],
            ['name' => 'Groot', 'role' => 'Tank', 'abilities' => 'Root Network, Bark Shield, Growth Spurt'],
            ['name' => 'Doctor Strange', 'role' => 'Tank', 'abilities' => 'Mirror Dimension, Time Manipulation, Shield Portals'],
            ['name' => 'Magneto', 'role' => 'Tank', 'abilities' => 'Metal Manipulation, Magnetic Shield, Levitation'],
            ['name' => 'Captain America', 'role' => 'Tank', 'abilities' => 'Shield Throw, Tactical Defense, Super Soldier'],
            ['name' => 'Venom', 'role' => 'Tank', 'abilities' => 'Symbiote Strike, Web Swing, Carnage Mode'],
            
            // Support
            ['name' => 'Storm', 'role' => 'Support', 'abilities' => 'Weather Control, Lightning Heal, Tornado'],
            ['name' => 'Mantis', 'role' => 'Support', 'abilities' => 'Empathic Healing, Sleep Spores, Nature\'s Blessing'],
            ['name' => 'Rocket Raccoon', 'role' => 'Support', 'abilities' => 'Tech Repair, Explosive Kit, Tactical Support'],
            ['name' => 'Luna Snow', 'role' => 'Support', 'abilities' => 'Ice Healing, Freeze Beam, Concert Boost'],
            ['name' => 'Adam Warlock', 'role' => 'Support', 'abilities' => 'Cosmic Healing, Soul Gem, Revival Cocoon'],
            ['name' => 'Cloak & Dagger', 'role' => 'Support', 'abilities' => 'Light Heal, Dark Teleport, Balanced Force'],
            ['name' => 'Jeff the Land Shark', 'role' => 'Support', 'abilities' => 'Healing Chomp, Bubble Shield, Shark Dive']
        ];
        
        foreach ($heroes as $hero) {
            DB::table('marvel_heroes')->updateOrInsert(
                ['name' => $hero['name']], 
                array_merge($hero, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }
    }
    
    private function seedLiveMatches()
    {
        $liveMatches = [
            [
                'team1_id' => 1,
                'team2_id' => 2,
                'status' => 'live',
                'format' => 'BO5',
                'team1_score' => 2,
                'team2_score' => 1,
                'scheduled_at' => Carbon::now()->addMinutes(30),
                'stream_url' => 'https://twitch.tv/marvelrivals',
                'current_map' => 'Tokyo 2099',
                'map_score' => '13-8',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'team1_id' => 3,
                'team2_id' => 4,
                'status' => 'upcoming',
                'format' => 'BO3',
                'team1_score' => 0,
                'team2_score' => 0,
                'scheduled_at' => Carbon::now()->addHours(2),
                'stream_url' => 'https://youtube.com/marvelrivals',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];
        
        foreach ($liveMatches as $match) {
            DB::table('matches')->insert($match);
        }
    }
    
    private function seedNewsArticles()
    {
        $news = [
            [
                'title' => 'Marvel Rivals Championship 2025 Announced',
                'slug' => 'marvel-rivals-championship-2025-announced',
                'excerpt' => 'The biggest Marvel Rivals tournament ever with $500K prize pool',
                'content' => 'NetEase Games announces the Marvel Rivals Championship 2025, featuring the largest prize pool in the game\'s history...',
                'category' => 'tournaments',
                'status' => 'published',
                'featured' => true,
                'author_id' => 1,
                'published_at' => Carbon::now()->subDays(2),
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2)
            ],
            [
                'title' => 'New Hero Jeff the Land Shark Joins the Roster',
                'slug' => 'new-hero-jeff-land-shark-joins-roster',
                'excerpt' => 'The adorable but deadly support hero makes his debut',
                'content' => 'Jeff the Land Shark brings unique support abilities to Marvel Rivals...',
                'category' => 'updates',
                'status' => 'published',
                'featured' => true,
                'author_id' => 1,
                'published_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1)
            ]
        ];
        
        foreach ($news as $article) {
            DB::table('news')->insert($article);
        }
    }
    
    private function seedMatchComments()
    {
        $comments = [
            [
                'match_id' => 1,
                'user_id' => 1,
                'content' => 'Amazing Spider-Man play! That wall-crawl flank was insane!',
                'created_at' => Carbon::now()->subMinutes(30),
                'updated_at' => Carbon::now()->subMinutes(30)
            ],
            [
                'match_id' => 1,
                'user_id' => 1,
                'content' => 'Hulk and Thor tank combo is too strong in this meta',
                'created_at' => Carbon::now()->subMinutes(15),
                'updated_at' => Carbon::now()->subMinutes(15)
            ]
        ];
        
        foreach ($comments as $comment) {
            DB::table('match_comments')->insert($comment);
        }
    }
    
    private function seedTournaments()
    {
        $tournaments = [
            [
                'name' => 'Marvel Rivals World Championship 2025',
                'description' => 'The ultimate Marvel Rivals tournament',
                'type' => 'international',
                'status' => 'upcoming',
                'start_date' => Carbon::now()->addMonths(2),
                'end_date' => Carbon::now()->addMonths(2)->addDays(7),
                'prize_pool' => '$500,000',
                'location' => 'Los Angeles, CA',
                'organizer' => 'NetEase Games',
                'format' => 'Double Elimination',
                'team_count' => 32,
                'registration_open' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Marvel Rivals NA Regional',
                'description' => 'North American regional championship',
                'type' => 'regional',
                'status' => 'live',
                'start_date' => Carbon::now()->subDays(3),
                'end_date' => Carbon::now()->addDays(4),
                'prize_pool' => '$100,000',
                'location' => 'Online',
                'organizer' => 'MRVL Esports',
                'format' => 'Swiss System',
                'team_count' => 16,
                'registration_open' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];
        
        foreach ($tournaments as $tournament) {
            DB::table('events')->insert($tournament);
        }
    }
}