<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Fix Events table - add all missing columns
        Schema::table('events', function (Blueprint $table) {
            // Add missing columns only if they don't exist
            if (!Schema::hasColumn('events', 'slug')) {
                $table->string('slug')->unique()->after('name');
            }
            if (!Schema::hasColumn('events', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('prize_pool');
            }
            if (!Schema::hasColumn('events', 'prize_distribution')) {
                $table->json('prize_distribution')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('events', 'region')) {
                $table->string('region')->nullable()->after('type');
            }
            if (!Schema::hasColumn('events', 'game_mode')) {
                $table->string('game_mode')->nullable()->after('region');
            }
            if (!Schema::hasColumn('events', 'registration_start')) {
                $table->timestamp('registration_start')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('events', 'registration_end')) {
                $table->timestamp('registration_end')->nullable()->after('registration_start');
            }
            if (!Schema::hasColumn('events', 'max_teams')) {
                $table->integer('max_teams')->default(16)->after('team_count');
            }
            if (!Schema::hasColumn('events', 'logo')) {
                $table->string('logo')->nullable()->after('description');
            }
            if (!Schema::hasColumn('events', 'banner')) {
                $table->string('banner')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('events', 'rules')) {
                $table->text('rules')->nullable()->after('description');
            }
            if (!Schema::hasColumn('events', 'registration_requirements')) {
                $table->json('registration_requirements')->nullable()->after('rules');
            }
            if (!Schema::hasColumn('events', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('end_date');
            }
            if (!Schema::hasColumn('events', 'featured')) {
                $table->boolean('featured')->default(false)->after('status');
            }
            if (!Schema::hasColumn('events', 'public')) {
                $table->boolean('public')->default(true)->after('featured');
            }
            if (!Schema::hasColumn('events', 'organizer_id')) {
                $table->unsignedBigInteger('organizer_id')->nullable()->after('organizer');
            }
            if (!Schema::hasColumn('events', 'streams')) {
                $table->json('streams')->nullable();
            }
            if (!Schema::hasColumn('events', 'social_links')) {
                $table->json('social_links')->nullable();
            }
        });

        // Generate slugs for existing events
        $events = DB::table('events')->whereNull('slug')->orWhere('slug', '')->get();
        foreach ($events as $event) {
            $slug = \Illuminate\Support\Str::slug($event->name);
            $counter = 1;
            $originalSlug = $slug;
            while (DB::table('events')->where('slug', $slug)->where('id', '!=', $event->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            DB::table('events')->where('id', $event->id)->update(['slug' => $slug]);
        }

        // Fix Forum threads table - add voting columns
        Schema::table('forum_threads', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_threads', 'upvotes')) {
                $table->integer('upvotes')->default(0)->after('views');
            }
            if (!Schema::hasColumn('forum_threads', 'downvotes')) {
                $table->integer('downvotes')->default(0)->after('upvotes');
            }
        });

        // Fix Forum posts table - add voting columns
        Schema::table('forum_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('forum_posts', 'upvotes')) {
                $table->integer('upvotes')->default(0);
            }
            if (!Schema::hasColumn('forum_posts', 'downvotes')) {
                $table->integer('downvotes')->default(0);
            }
        });

        // Fix News table - add missing columns
        Schema::table('news', function (Blueprint $table) {
            if (!Schema::hasColumn('news', 'featured_image')) {
                $table->string('featured_image')->nullable()->after('content');
            }
            if (!Schema::hasColumn('news', 'video_url')) {
                $table->string('video_url')->nullable()->after('featured_image');
            }
            if (!Schema::hasColumn('news', 'mentions')) {
                $table->json('mentions')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('news', 'author_id')) {
                $table->unsignedBigInteger('author_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('news', 'status')) {
                $table->enum('status', ['draft', 'pending', 'published', 'archived'])->default('published')->after('featured');
            }
            if (!Schema::hasColumn('news', 'featured')) {
                $table->boolean('featured')->default(false)->after('views');
            }
            if (!Schema::hasColumn('news', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('status');
            }
        });

        // Create Rankings table if not exists
        if (!Schema::hasTable('rankings')) {
            Schema::create('rankings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('player_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->enum('type', ['player', 'team']);
                $table->integer('rank')->default(0);
                $table->integer('rating')->default(1000);
                $table->integer('wins')->default(0);
                $table->integer('losses')->default(0);
                $table->integer('matches_played')->default(0);
                $table->float('win_rate')->default(0);
                $table->string('current_rank')->default('bronze_iii');
                $table->integer('rank_points')->default(0);
                $table->json('hero_stats')->nullable();
                $table->json('performance_stats')->nullable();
                $table->integer('season')->default(1);
                $table->timestamps();
                
                $table->index('player_id');
                $table->index('team_id');
                $table->index(['type', 'rating']);
                $table->index('season');
            });
        }

        // Ensure marvel_rivals_heroes table exists with correct structure
        if (!Schema::hasTable('marvel_rivals_heroes')) {
            Schema::create('marvel_rivals_heroes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->enum('role', ['Vanguard', 'Duelist', 'Strategist']);
                $table->text('description')->nullable();
                $table->json('abilities')->nullable();
                $table->string('difficulty')->nullable();
                $table->json('stats')->nullable();
                $table->string('ultimate_ability')->nullable();
                $table->string('passive_ability')->nullable();
                $table->integer('health')->default(250);
                $table->string('season_added')->default('Launch');
                $table->boolean('is_new')->default(false);
                $table->integer('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
                
                $table->index('role');
                $table->index('active');
            });

            // Insert all Marvel Rivals heroes
            $this->insertMarvelRivalsHeroes();
        }

        // Create forum_votes table for proper voting system
        if (!Schema::hasTable('forum_votes')) {
            Schema::create('forum_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->morphs('votable');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['user_id', 'votable_id', 'votable_type']);
                $table->index(['votable_id', 'votable_type']);
            });
        }

        // Create news_votes table
        if (!Schema::hasTable('news_votes')) {
            Schema::create('news_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('news_id');
                $table->enum('vote_type', ['upvote', 'downvote']);
                $table->timestamps();
                
                $table->unique(['user_id', 'news_id']);
                $table->index('news_id');
            });
        }

        // Add missing columns to brackets table
        if (Schema::hasTable('brackets')) {
            Schema::table('brackets', function (Blueprint $table) {
                if (!Schema::hasColumn('brackets', 'bracket_data')) {
                    $table->json('bracket_data')->nullable();
                }
            });
        }

        // Ensure match_player_stats has all needed columns
        if (Schema::hasTable('match_player_stats')) {
            Schema::table('match_player_stats', function (Blueprint $table) {
                if (!Schema::hasColumn('match_player_stats', 'team_id')) {
                    $table->unsignedBigInteger('team_id')->nullable()->after('player_id');
                }
            });
        }
    }

    private function insertMarvelRivalsHeroes()
    {
        $heroes = [
            // Vanguards
            ['name' => 'Venom', 'slug' => 'venom', 'role' => 'Vanguard', 'health' => 650, 'difficulty' => 'Easy'],
            ['name' => 'Groot', 'slug' => 'groot', 'role' => 'Vanguard', 'health' => 700, 'difficulty' => 'Easy'],
            ['name' => 'Magneto', 'slug' => 'magneto', 'role' => 'Vanguard', 'health' => 600, 'difficulty' => 'Hard'],
            ['name' => 'Captain America', 'slug' => 'captain-america', 'role' => 'Vanguard', 'health' => 600, 'difficulty' => 'Medium'],
            ['name' => 'Thor', 'slug' => 'thor', 'role' => 'Vanguard', 'health' => 650, 'difficulty' => 'Medium'],
            ['name' => 'Hulk', 'slug' => 'hulk', 'role' => 'Vanguard', 'health' => 800, 'difficulty' => 'Easy'],
            ['name' => 'Doctor Strange', 'slug' => 'doctor-strange', 'role' => 'Vanguard', 'health' => 600, 'difficulty' => 'Hard'],
            ['name' => 'Peni Parker', 'slug' => 'peni-parker', 'role' => 'Vanguard', 'health' => 550, 'difficulty' => 'Medium'],
            
            // Duelists
            ['name' => 'Spider-Man', 'slug' => 'spider-man', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Iron Man', 'slug' => 'iron-man', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Black Widow', 'slug' => 'black-widow', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Hard'],
            ['name' => 'Hawkeye', 'slug' => 'hawkeye', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Scarlet Witch', 'slug' => 'scarlet-witch', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Easy'],
            ['name' => 'Storm', 'slug' => 'storm', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Star-Lord', 'slug' => 'star-lord', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Easy'],
            ['name' => 'Black Panther', 'slug' => 'black-panther', 'role' => 'Duelist', 'health' => 275, 'difficulty' => 'Hard'],
            ['name' => 'Magik', 'slug' => 'magik', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Moon Knight', 'slug' => 'moon-knight', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Namor', 'slug' => 'namor', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Psylocke', 'slug' => 'psylocke', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Hard'],
            ['name' => 'Punisher', 'slug' => 'punisher', 'role' => 'Duelist', 'health' => 275, 'difficulty' => 'Easy'],
            ['name' => 'Winter Soldier', 'slug' => 'winter-soldier', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Iron Fist', 'slug' => 'iron-fist', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Squirrel Girl', 'slug' => 'squirrel-girl', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Easy'],
            ['name' => 'Hela', 'slug' => 'hela', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Wolverine', 'slug' => 'wolverine', 'role' => 'Duelist', 'health' => 300, 'difficulty' => 'Easy'],
            ['name' => 'Emma Frost', 'slug' => 'emma-frost', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Hard', 'is_new' => true, 'season_added' => 'Season 2'],
            ['name' => 'Mr. Fantastic', 'slug' => 'mr-fantastic', 'role' => 'Duelist', 'health' => 250, 'difficulty' => 'Medium', 'is_new' => true, 'season_added' => 'Season 2'],
            
            // Strategists
            ['name' => 'Adam Warlock', 'slug' => 'adam-warlock', 'role' => 'Strategist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Jeff the Land Shark', 'slug' => 'jeff-the-land-shark', 'role' => 'Strategist', 'health' => 225, 'difficulty' => 'Easy'],
            ['name' => 'Luna Snow', 'slug' => 'luna-snow', 'role' => 'Strategist', 'health' => 250, 'difficulty' => 'Medium'],
            ['name' => 'Mantis', 'slug' => 'mantis', 'role' => 'Strategist', 'health' => 250, 'difficulty' => 'Easy'],
            ['name' => 'Rocket Raccoon', 'slug' => 'rocket-raccoon', 'role' => 'Strategist', 'health' => 225, 'difficulty' => 'Medium'],
            ['name' => 'Loki', 'slug' => 'loki', 'role' => 'Strategist', 'health' => 250, 'difficulty' => 'Hard'],
            ['name' => 'Cloak & Dagger', 'slug' => 'cloak-and-dagger', 'role' => 'Strategist', 'health' => 275, 'difficulty' => 'Medium'],
            ['name' => 'Bruce Banner', 'slug' => 'bruce-banner', 'role' => 'Strategist', 'health' => 250, 'difficulty' => 'Hard', 'is_new' => true, 'season_added' => 'Season 1']
        ];

        foreach ($heroes as $index => $hero) {
            DB::table('marvel_rivals_heroes')->updateOrInsert(
                ['slug' => $hero['slug']],
                array_merge($hero, [
                    'sort_order' => $index,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ])
            );
        }
    }

    public function down()
    {
        // Remove added columns
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'slug', 'currency', 'prize_distribution', 'region', 'game_mode',
                'registration_start', 'registration_end', 'max_teams', 'logo', 'banner',
                'rules', 'registration_requirements', 'timezone', 'featured', 'public',
                'organizer_id', 'streams', 'social_links'
            ]);
        });

        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropColumn(['upvotes', 'downvotes']);
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['upvotes', 'downvotes']);
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropColumn(['featured_image', 'video_url', 'mentions', 'author_id', 'status', 'featured', 'published_at']);
        });

        // Drop created tables
        Schema::dropIfExists('rankings');
        Schema::dropIfExists('marvel_rivals_heroes');
        Schema::dropIfExists('forum_votes');
        Schema::dropIfExists('news_votes');
    }
};