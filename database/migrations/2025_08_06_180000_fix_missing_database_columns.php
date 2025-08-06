<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix events table - add missing min_teams column
        if (Schema::hasTable('events') && !Schema::hasColumn('events', 'min_teams')) {
            Schema::table('events', function (Blueprint $table) {
                $table->integer('min_teams')->default(8)->after('max_teams');
            });
        }

        // Fix teams table - add missing columns with defaults
        if (Schema::hasTable('teams')) {
            if (!Schema::hasColumn('teams', 'short_name')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->string('short_name', 10)->nullable()->after('name');
                });
            }
            
            if (!Schema::hasColumn('teams', 'rank')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->integer('rank')->default(0)->after('rating');
                });
            }
        }

        // Fix players table - add missing columns
        if (Schema::hasTable('players')) {
            if (!Schema::hasColumn('players', 'flag')) {
                Schema::table('players', function (Blueprint $table) {
                    $table->string('flag', 10)->nullable()->after('country');
                });
            }
            
            if (!Schema::hasColumn('players', 'main_hero')) {
                Schema::table('players', function (Blueprint $table) {
                    $table->string('main_hero')->nullable()->after('role');
                });
            }
        }

        // Fix player_team_history table - add missing columns
        if (Schema::hasTable('player_team_history')) {
            if (!Schema::hasColumn('player_team_history', 'change_date')) {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->datetime('change_date')->default(DB::raw('CURRENT_TIMESTAMP'))->after('player_id');
                });
            }
            
            if (!Schema::hasColumn('player_team_history', 'team_id')) {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->unsignedBigInteger('team_id')->nullable()->after('player_id');
                    $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
                });
            }

            // Fix change_type enum if it exists
            if (Schema::hasColumn('player_team_history', 'change_type')) {
                DB::statement("ALTER TABLE player_team_history MODIFY COLUMN change_type ENUM('join', 'leave', 'transfer', 'promotion', 'demotion') DEFAULT 'join'");
            } else {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->enum('change_type', ['join', 'leave', 'transfer', 'promotion', 'demotion'])->default('join')->after('team_id');
                });
            }
        }

        // Fix matches table - add missing scheduled_at column
        if (Schema::hasTable('matches') && !Schema::hasColumn('matches', 'scheduled_at')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->datetime('scheduled_at')->nullable()->after('event_id');
            });
        }

        // Update existing teams to have unique short_name based on name
        if (Schema::hasTable('teams') && Schema::hasColumn('teams', 'short_name')) {
            $teams = DB::table('teams')->whereNull('short_name')->orWhere('short_name', '')->get();
            
            foreach ($teams as $team) {
                $shortName = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $team->name), 0, 3));
                if (strlen($shortName) < 2) {
                    $shortName = strtoupper(substr($team->name, 0, 3));
                }
                
                // Ensure uniqueness
                $counter = 1;
                $originalShortName = $shortName;
                while (DB::table('teams')->where('short_name', $shortName)->where('id', '!=', $team->id)->exists()) {
                    $shortName = $originalShortName . $counter;
                    $counter++;
                }
                
                DB::table('teams')->where('id', $team->id)->update(['short_name' => $shortName]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'min_teams')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('min_teams');
            });
        }

        if (Schema::hasTable('teams')) {
            if (Schema::hasColumn('teams', 'short_name')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropColumn('short_name');
                });
            }
            if (Schema::hasColumn('teams', 'rank')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->dropColumn('rank');
                });
            }
        }

        if (Schema::hasTable('players')) {
            if (Schema::hasColumn('players', 'flag')) {
                Schema::table('players', function (Blueprint $table) {
                    $table->dropColumn('flag');
                });
            }
            if (Schema::hasColumn('players', 'main_hero')) {
                Schema::table('players', function (Blueprint $table) {
                    $table->dropColumn('main_hero');
                });
            }
        }

        if (Schema::hasTable('player_team_history')) {
            if (Schema::hasColumn('player_team_history', 'change_date')) {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->dropColumn('change_date');
                });
            }
            if (Schema::hasColumn('player_team_history', 'team_id')) {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->dropForeign(['team_id']);
                    $table->dropColumn('team_id');
                });
            }
            if (Schema::hasColumn('player_team_history', 'change_type')) {
                Schema::table('player_team_history', function (Blueprint $table) {
                    $table->dropColumn('change_type');
                });
            }
        }

        if (Schema::hasTable('matches') && Schema::hasColumn('matches', 'scheduled_at')) {
            Schema::table('matches', function (Blueprint $table) {
                $table->dropColumn('scheduled_at');
            });
        }
    }
};