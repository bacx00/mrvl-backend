<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MarvelRivalsCompleteSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Starting Marvel Rivals Complete Seeder...');
        
        // Step 1: ONLY seed forum threads (fixes the 404 error)
        $this->seedForumThreads();
        
        // Step 2: Check existing data before seeding other content
        $this->checkAndSeedSafely();
        
        $this->command->info('Marvel Rivals seeding completed successfully!');
    }
    
    private function seedForumThreads()
    {
        $this->command->info('Seeding forum threads...');
        
        $threads = [
            [
                'id' => 15,
                'title' => 'Marvel Rivals World Championship 2025',
                'content' => 'The biggest Marvel Rivals tournament is coming! Prize pool: $500,000. Registration opens next week. Who do you think will dominate this year?',
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
                'content' => 'Updated tier list for support heroes based on recent patch changes. Luna Snow still S-tier! What are your thoughts on the current meta?',
                'category' => 'strategies',
                'views' => 456,
                'replies' => 23,
                'pinned' => false,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(1)
            ],
            [
                'id' => 17,
                'title' => 'Best Tank Compositions for Ranked',
                'content' => 'Let\'s discuss the most effective tank compositions in the current meta. Hulk + Magneto seems to be dominating ranked matches.',
                'category' => 'strategies',
                'views' => 789,
                'replies' => 42,
                'pinned' => false,
                'locked' => false,
                'user_id' => 1,
                'created_at' => Carbon::now()->subDays(3),
                'updated_at' => Carbon::now()->subHours(2)
            ]
        ];
        
        foreach ($threads as $thread) {
            // Use updateOrInsert to avoid duplicates
            DB::table('forum_threads')->updateOrInsert(
                ['id' => $thread['id']], 
                $thread
            );
            $this->command->info("- Created/updated forum thread: {$thread['title']}");
        }
    }
    
    private function checkAndSeedSafely()
    {
        $this->command->info('Checking existing data before seeding...');
        
        // Check what teams exist
        $teams = DB::table('teams')->pluck('id')->toArray();
        $this->command->info('Existing teams: ' . implode(', ', $teams));
        
        // Check what users exist  
        $users = DB::table('users')->pluck('id')->toArray();
        $this->command->info('Existing users: ' . implode(', ', $users));
        
        // Only seed Marvel heroes if the table exists and is empty
        if (Schema::hasTable('marvel_heroes')) {
            $heroCount = DB::table('marvel_heroes')->count();
            if ($heroCount === 0) {
                $this->seedMarvelHeroes();
            } else {
                $this->command->info("Marvel heroes already exist ({$heroCount} heroes)");
            }
        }
        
        // Only seed matches if we have valid teams
        if (count($teams) >= 2) {
            $this->seedSafeMatches($teams);
        } else {
            $this->command->info('Skipping match seeding - need at least 2 teams');
        }
        
        // Only seed news if we have valid users
        if (count($users) >= 1) {
            $this->seedNewsArticles($users[0]);
        } else {
            $this->command->info('Skipping news seeding - need at least 1 user');
        }
    }
    
    private function seedMarvelHeroes()
    {
        $this->command->info('Seeding Marvel heroes...');
        
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
        
        $this->command->info('- Added ' . count($heroes) . ' Marvel heroes');
    }
    
    private function seedSafeMatches($teamIds)
    {
        $this->command->info('Seeding safe matches with existing teams...');
        
        if (count($teamIds) < 2) return;
        
        $matches = [
            [
                'team1_id' => $teamIds[0],
                'team2_id' => $teamIds[1],
                'status' => 'upcoming',
                'format' => 'BO3',
                'team1_score' => 0,
                'team2_score' => 0,
                'scheduled_at' => Carbon::now()->addHours(2),
                'stream_url' => 'https://twitch.tv/marvelrivals',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]
        ];
        
        foreach ($matches as $match) {
            // Check if match already exists
            $existing = DB::table('matches')
                ->where('team1_id', $match['team1_id'])
                ->where('team2_id', $match['team2_id'])
                ->where('status', $match['status'])
                ->first();
                
            if (!$existing) {
                DB::table('matches')->insert($match);
                $this->command->info("- Created match between teams {$match['team1_id']} vs {$match['team2_id']}");
            }
        }
    }
    
    private function seedNewsArticles($userId)
    {
        $this->command->info('Seeding news articles...');
        
        $news = [
            [
                'title' => 'Marvel Rivals Championship 2025 Announced',
                'slug' => 'marvel-rivals-championship-2025-announced',
                'excerpt' => 'The biggest Marvel Rivals tournament ever with $500K prize pool',
                'content' => 'NetEase Games announces the Marvel Rivals Championship 2025, featuring the largest prize pool in the game\'s history. Teams from around the world will compete for glory and a massive $500,000 prize pool.',
                'category' => 'tournaments',
                'status' => 'published',
                'featured' => true,
                'author_id' => $userId,
                'published_at' => Carbon::now()->subDays(2),
                'created_at' => Carbon::now()->subDays(2),
                'updated_at' => Carbon::now()->subDays(2)
            ],
            [
                'title' => 'New Hero Jeff the Land Shark Joins the Roster',
                'slug' => 'new-hero-jeff-land-shark-joins-roster',
                'excerpt' => 'The adorable but deadly support hero makes his debut',
                'content' => 'Jeff the Land Shark brings unique support abilities to Marvel Rivals. This loveable character offers powerful healing abilities and crowd control that can turn the tide of battle.',
                'category' => 'updates',
                'status' => 'published',
                'featured' => true,
                'author_id' => $userId,
                'published_at' => Carbon::now()->subDays(1),
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1)
            ]
        ];
        
        foreach ($news as $article) {
            // Check if article exists
            $existing = DB::table('news')->where('slug', $article['slug'])->first();
            if (!$existing) {
                DB::table('news')->insert($article);
                $this->command->info("- Created news article: {$article['title']}");
            }
        }
    }
}