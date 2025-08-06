<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix earnings data type and ELO rating issues
     */
    public function up()
    {
        // Fix teams table earnings and ELO issues
        if (Schema::hasTable('teams')) {
            Schema::table('teams', function (Blueprint $table) {
                // Change earnings from string to decimal for proper calculations
                if (!Schema::hasColumn('teams', 'earnings_amount')) {
                    $table->decimal('earnings_amount', 15, 2)->default(0.00)->after('earnings');
                }
                if (!Schema::hasColumn('teams', 'earnings_currency')) {
                    $table->string('earnings_currency', 3)->default('USD')->after('earnings_amount');
                }
                
                // Add ELO tracking fields
                if (!Schema::hasColumn('teams', 'elo_rating')) {
                    $table->integer('elo_rating')->default(1000)->after('rating');
                }
                if (!Schema::hasColumn('teams', 'peak_elo')) {
                    $table->integer('peak_elo')->default(1000)->after('elo_rating');
                }
                if (!Schema::hasColumn('teams', 'elo_changes')) {
                    $table->integer('elo_changes')->default(0)->after('peak_elo'); // Track total ELO gained/lost
                }
                if (!Schema::hasColumn('teams', 'last_elo_update')) {
                    $table->timestamp('last_elo_update')->nullable()->after('elo_changes');
                }
                
                // Add win/loss tracking for better statistics
                if (!Schema::hasColumn('teams', 'matches_played')) {
                    $table->integer('matches_played')->default(0)->after('losses');
                }
                if (!Schema::hasColumn('teams', 'maps_won')) {
                    $table->integer('maps_won')->default(0)->after('matches_played');
                }
                if (!Schema::hasColumn('teams', 'maps_lost')) {
                    $table->integer('maps_lost')->default(0)->after('maps_won');
                }
                if (!Schema::hasColumn('teams', 'map_win_rate')) {
                    $table->decimal('map_win_rate', 5, 2)->default(0.00)->after('maps_lost');
                }
                
                // Add performance tracking
                if (!Schema::hasColumn('teams', 'recent_performance')) {
                    $table->json('recent_performance')->nullable()->after('map_win_rate');
                }
                if (!Schema::hasColumn('teams', 'longest_win_streak')) {
                    $table->integer('longest_win_streak')->default(0)->after('recent_performance');
                }
                if (!Schema::hasColumn('teams', 'current_streak_count')) {
                    $table->integer('current_streak_count')->default(0)->after('longest_win_streak');
                }
                if (!Schema::hasColumn('teams', 'current_streak_type')) {
                    $table->enum('current_streak_type', ['win', 'loss', 'none'])->default('none')->after('current_streak_count');
                }
            });
            
            // Add indexes separately to avoid conflicts
            try {
                Schema::table('teams', function (Blueprint $table) {
                    if (Schema::hasColumn('teams', 'elo_rating') && Schema::hasColumn('teams', 'region')) {
                        $table->index(['elo_rating', 'region'], 'teams_elo_region_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
            
            try {
                Schema::table('teams', function (Blueprint $table) {
                    if (Schema::hasColumn('teams', 'earnings_amount')) {
                        $table->index(['earnings_amount'], 'teams_earnings_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
            
            try {
                Schema::table('teams', function (Blueprint $table) {
                    if (Schema::hasColumn('teams', 'last_elo_update')) {
                        $table->index(['last_elo_update'], 'teams_last_elo_update_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
        }

        // Fix players table earnings and ELO issues
        if (Schema::hasTable('players')) {
            Schema::table('players', function (Blueprint $table) {
                // Change earnings from string to decimal for proper calculations
                if (!Schema::hasColumn('players', 'earnings_amount')) {
                    $table->decimal('earnings_amount', 15, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'earnings_currency')) {
                    $table->string('earnings_currency', 3)->default('USD');
                }
                
                // Add ELO tracking fields
                if (!Schema::hasColumn('players', 'elo_rating')) {
                    $table->integer('elo_rating')->default(1000);
                }
                if (!Schema::hasColumn('players', 'peak_elo')) {
                    $table->integer('peak_elo')->default(1000);
                }
                if (!Schema::hasColumn('players', 'elo_changes')) {
                    $table->integer('elo_changes')->default(0);
                }
                if (!Schema::hasColumn('players', 'last_elo_update')) {
                    $table->timestamp('last_elo_update')->nullable();
                }
                
                // Add comprehensive career statistics (need to add total_matches and total_wins first)
                if (!Schema::hasColumn('players', 'total_matches')) {
                    $table->integer('total_matches')->default(0);
                }
                if (!Schema::hasColumn('players', 'total_wins')) {
                    $table->integer('total_wins')->default(0);
                }
                if (!Schema::hasColumn('players', 'total_eliminations')) {
                    $table->integer('total_eliminations')->default(0);
                }
                if (!Schema::hasColumn('players', 'total_deaths')) {
                    $table->integer('total_deaths')->default(0);
                }
                if (!Schema::hasColumn('players', 'total_assists')) {
                    $table->integer('total_assists')->default(0);
                }
                if (!Schema::hasColumn('players', 'overall_kda')) {
                    $table->decimal('overall_kda', 8, 2)->default(0.00);
                }
                
                // Add missing statistics columns that are referenced in Player model
                if (!Schema::hasColumn('players', 'total_maps_played')) {
                    $table->integer('total_maps_played')->default(0);
                }
                if (!Schema::hasColumn('players', 'avg_rating')) {
                    $table->decimal('avg_rating', 8, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_combat_score')) {
                    $table->decimal('avg_combat_score', 8, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_kda')) {
                    $table->decimal('avg_kda', 8, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_damage_per_round')) {
                    $table->decimal('avg_damage_per_round', 8, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_kast')) {
                    $table->decimal('avg_kast', 5, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_kills_per_round')) {
                    $table->decimal('avg_kills_per_round', 5, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_assists_per_round')) {
                    $table->decimal('avg_assists_per_round', 5, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_first_kills_per_round')) {
                    $table->decimal('avg_first_kills_per_round', 5, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'avg_first_deaths_per_round')) {
                    $table->decimal('avg_first_deaths_per_round', 5, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'hero_pool')) {
                    $table->json('hero_pool')->nullable();
                }
                if (!Schema::hasColumn('players', 'career_stats')) {
                    $table->json('career_stats')->nullable();
                }
                if (!Schema::hasColumn('players', 'achievements')) {
                    $table->json('achievements')->nullable();
                }
                
                // Add performance metrics
                if (!Schema::hasColumn('players', 'average_damage_per_match')) {
                    $table->decimal('average_damage_per_match', 10, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'average_healing_per_match')) {
                    $table->decimal('average_healing_per_match', 10, 2)->default(0.00);
                }
                if (!Schema::hasColumn('players', 'average_damage_blocked_per_match')) {
                    $table->decimal('average_damage_blocked_per_match', 10, 2)->default(0.00);
                }
                
                // Add hero specialization tracking
                if (!Schema::hasColumn('players', 'hero_statistics')) {
                    $table->json('hero_statistics')->nullable();
                }
                if (!Schema::hasColumn('players', 'most_played_hero')) {
                    $table->string('most_played_hero')->nullable();
                }
                if (!Schema::hasColumn('players', 'best_winrate_hero')) {
                    $table->string('best_winrate_hero')->nullable();
                }
                
                // Add streak tracking
                if (!Schema::hasColumn('players', 'longest_win_streak')) {
                    $table->integer('longest_win_streak')->default(0);
                }
                if (!Schema::hasColumn('players', 'current_win_streak')) {
                    $table->integer('current_win_streak')->default(0);
                }
            });
            
            // Add indexes separately to avoid conflicts
            try {
                Schema::table('players', function (Blueprint $table) {
                    if (Schema::hasColumn('players', 'elo_rating') && Schema::hasColumn('players', 'role')) {
                        $table->index(['elo_rating', 'role'], 'players_elo_role_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
            
            try {
                Schema::table('players', function (Blueprint $table) {
                    if (Schema::hasColumn('players', 'earnings_amount')) {
                        $table->index(['earnings_amount'], 'players_earnings_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
            
            try {
                Schema::table('players', function (Blueprint $table) {
                    if (Schema::hasColumn('players', 'total_matches') && Schema::hasColumn('players', 'total_wins')) {
                        $table->index(['total_matches', 'total_wins'], 'players_performance_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
            
            try {
                Schema::table('players', function (Blueprint $table) {
                    if (Schema::hasColumn('players', 'last_elo_update')) {
                        $table->index(['last_elo_update'], 'players_last_elo_update_idx');
                    }
                });
            } catch (\Exception $e) {
                // Index already exists or other error, continue
            }
        }

        // Create match results cache table for faster queries
        Schema::create('match_results_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('player_id')->nullable()->constrained('players')->onDelete('cascade');
            
            // Match outcome
            $table->enum('result', ['win', 'loss'])->index();
            $table->integer('team_score');
            $table->integer('opponent_score');
            $table->integer('map_differential'); // maps won - maps lost
            
            // ELO changes
            $table->integer('elo_before');
            $table->integer('elo_after');
            $table->integer('elo_change');
            
            // Performance metrics (for players)
            $table->integer('eliminations')->default(0);
            $table->integer('deaths')->default(0);
            $table->integer('assists')->default(0);
            $table->decimal('kda_ratio', 8, 2)->default(0.00);
            $table->integer('damage_dealt')->default(0);
            $table->integer('healing_done')->default(0);
            $table->integer('damage_blocked')->default(0);
            
            // Earnings awarded
            $table->decimal('earnings_awarded', 15, 2)->default(0.00);
            
            $table->timestamp('match_date');
            $table->timestamps();
            
            // Compound indexes for fast lookups
            $table->index(['team_id', 'match_date'], 'team_match_date_idx');
            $table->index(['player_id', 'match_date'], 'player_match_date_idx');
            $table->index(['result', 'match_date'], 'result_date_idx');
            $table->unique(['match_id', 'team_id', 'player_id'], 'match_team_player_unique');
        });

        // Create earnings history table for audit trail
        Schema::create('earnings_history', function (Blueprint $table) {
            $table->id();
            $table->morphs('earnable'); // team or player
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('type', ['tournament_prize', 'match_reward', 'sponsorship', 'adjustment']);
            $table->string('source')->nullable(); // tournament/match/sponsor name
            $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('set null');
            $table->text('description')->nullable();
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->timestamp('awarded_at');
            $table->timestamps();
            
            $table->index(['earnable_type', 'earnable_id', 'awarded_at'], 'earnings_earnable_date_idx');
            $table->index(['type', 'awarded_at'], 'earnings_type_date_idx');
        });

        // Create ELO history table for rating tracking
        Schema::create('elo_history', function (Blueprint $table) {
            $table->id();
            $table->morphs('ratable'); // team or player
            $table->integer('rating_before');
            $table->integer('rating_after');
            $table->integer('rating_change');
            $table->foreignId('match_id')->nullable()->constrained('matches')->onDelete('set null');
            $table->enum('change_reason', ['match_win', 'match_loss', 'tournament_bonus', 'inactivity_decay', 'manual_adjustment']);
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->index(['ratable_type', 'ratable_id', 'changed_at'], 'elo_ratable_date_idx');
            $table->index(['change_reason', 'changed_at'], 'elo_reason_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('elo_history');
        Schema::dropIfExists('earnings_history');
        Schema::dropIfExists('match_results_cache');
        
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_elo_region_idx');
            $table->dropIndex('teams_earnings_idx');
            $table->dropIndex('teams_last_elo_update_idx');
            
            $table->dropColumn([
                'earnings_amount', 'earnings_currency', 'elo_rating', 'peak_elo', 'elo_changes',
                'last_elo_update', 'matches_played', 'maps_won', 'maps_lost', 'map_win_rate',
                'recent_performance', 'longest_win_streak', 'current_streak_count', 'current_streak_type'
            ]);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropIndex('players_elo_role_idx');
            $table->dropIndex('players_earnings_idx');
            $table->dropIndex('players_performance_idx');
            $table->dropIndex('players_last_elo_update_idx');
            
            $table->dropColumn([
                'earnings_amount', 'earnings_currency', 'elo_rating', 'peak_elo', 'elo_changes',
                'last_elo_update', 'total_eliminations', 'total_deaths', 'total_assists', 'overall_kda',
                'average_damage_per_match', 'average_healing_per_match', 'average_damage_blocked_per_match',
                'hero_statistics', 'most_played_hero', 'best_winrate_hero', 'longest_win_streak', 'current_win_streak'
            ]);
        });
    }
};