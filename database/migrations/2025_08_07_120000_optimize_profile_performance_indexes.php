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
        // Add performance indexes for profile customization system
        Schema::table('users', function (Blueprint $table) {
            // Index for hero_flair lookups - used in profile queries and hero validation
            if (!$this->indexExists('users', 'idx_users_hero_flair')) {
                $table->index('hero_flair', 'idx_users_hero_flair');
            }
            
            // Composite index for profile queries - optimizes queries that filter by both flairs and status
            if (!$this->indexExists('users', 'idx_users_profile_lookup')) {
                $table->index(['team_flair_id', 'hero_flair', 'status'], 'idx_users_profile_lookup');
            }
            
            // Index for avatar-related queries
            if (!$this->indexExists('users', 'idx_users_avatar_type')) {
                $table->index(['use_hero_as_avatar', 'hero_flair'], 'idx_users_avatar_type');
            }
            
            // Index for flair display preferences
            if (!$this->indexExists('users', 'idx_users_flair_display')) {
                $table->index(['show_hero_flair', 'show_team_flair'], 'idx_users_flair_display');
            }
        });

        // Add performance indexes for heroes table
        Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
            // Index for role-based queries (used in getAvailableFlairs)
            if (!$this->indexExists('marvel_rivals_heroes', 'idx_heroes_role_name')) {
                $table->index(['role', 'name'], 'idx_heroes_role_name');
            }
            
            // Index for active heroes with sort order
            if (!$this->indexExists('marvel_rivals_heroes', 'idx_heroes_active_sort')) {
                $table->index(['active', 'sort_order'], 'idx_heroes_active_sort');
            }
        });

        // Add performance indexes for teams table
        Schema::table('teams', function (Blueprint $table) {
            // Index for region-based team lookups (used in getAvailableFlairs)
            if (!$this->indexExists('teams', 'idx_teams_region_name')) {
                $table->index(['region', 'name'], 'idx_teams_region_name');
            }
            
            // Index for team lookup by name (for validation)
            if (!$this->indexExists('teams', 'idx_teams_name')) {
                $table->index('name', 'idx_teams_name');
            }
        });
        
        // Add indexes for frequently queried user activity tables
        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                if (!$this->indexExists('user_activities', 'idx_user_activities_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_user_activities_user_created');
                }
                if (!$this->indexExists('user_activities', 'idx_user_activities_action_created')) {
                    $table->index(['action', 'created_at'], 'idx_user_activities_action_created');
                }
            });
        }

        // Add indexes for voting system (used in user stats)
        if (Schema::hasTable('votes')) {
            Schema::table('votes', function (Blueprint $table) {
                if (!$this->indexExists('votes', 'idx_votes_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_votes_user_created');
                }
                if (!$this->indexExists('votes', 'idx_votes_voteable')) {
                    $table->index(['voteable_type', 'voteable_id', 'vote'], 'idx_votes_voteable');
                }
            });
        }

        // Add indexes for mentions system (used in user stats)
        if (Schema::hasTable('mentions')) {
            Schema::table('mentions', function (Blueprint $table) {
                if (!$this->indexExists('mentions', 'idx_mentions_mentioned_by')) {
                    $table->index(['mentioned_by', 'created_at'], 'idx_mentions_mentioned_by');
                }
                if (!$this->indexExists('mentions', 'idx_mentions_mentioned_to')) {
                    $table->index(['mentioned_type', 'mentioned_id'], 'idx_mentions_mentioned_to');
                }
            });
        }

        // Add indexes for forum tables (used in user stats)
        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                if (!$this->indexExists('forum_threads', 'idx_forum_threads_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_forum_threads_user_created');
                }
            });
        }

        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                if (!$this->indexExists('forum_posts', 'idx_forum_posts_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_forum_posts_user_created');
                }
                if (!$this->indexExists('forum_posts', 'idx_forum_posts_thread_created')) {
                    $table->index(['thread_id', 'created_at'], 'idx_forum_posts_thread_created');
                }
            });
        }

        // Add indexes for comment tables (used in user stats)
        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                if (!$this->indexExists('news_comments', 'idx_news_comments_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_news_comments_user_created');
                }
                if (!$this->indexExists('news_comments', 'idx_news_comments_news_created')) {
                    $table->index(['news_id', 'created_at'], 'idx_news_comments_news_created');
                }
            });
        }

        if (Schema::hasTable('match_comments')) {
            Schema::table('match_comments', function (Blueprint $table) {
                if (!$this->indexExists('match_comments', 'idx_match_comments_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_match_comments_user_created');
                }
                if (!$this->indexExists('match_comments', 'idx_match_comments_match_created')) {
                    $table->index(['match_id', 'created_at'], 'idx_match_comments_match_created');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove performance indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_hero_flair');
            $table->dropIndex('idx_users_profile_lookup');
            $table->dropIndex('idx_users_avatar_type');
            $table->dropIndex('idx_users_flair_display');
        });

        Schema::table('marvel_rivals_heroes', function (Blueprint $table) {
            $table->dropIndex('idx_heroes_role_name');
            $table->dropIndex('idx_heroes_active_sort');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('idx_teams_region_name');
            $table->dropIndex('idx_teams_name');
        });

        if (Schema::hasTable('user_activities')) {
            Schema::table('user_activities', function (Blueprint $table) {
                $table->dropIndex('idx_user_activities_user_created');
                $table->dropIndex('idx_user_activities_type_created');
            });
        }

        if (Schema::hasTable('votes')) {
            Schema::table('votes', function (Blueprint $table) {
                $table->dropIndex('idx_votes_user_created');
                $table->dropIndex('idx_votes_voteable');
            });
        }

        if (Schema::hasTable('mentions')) {
            Schema::table('mentions', function (Blueprint $table) {
                $table->dropIndex('idx_mentions_mentioned_by');
                $table->dropIndex('idx_mentions_mentioned_to');
            });
        }

        if (Schema::hasTable('forum_threads')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->dropIndex('idx_forum_threads_user_created');
            });
        }

        if (Schema::hasTable('forum_posts')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->dropIndex('idx_forum_posts_user_created');
                $table->dropIndex('idx_forum_posts_thread_created');
            });
        }

        if (Schema::hasTable('news_comments')) {
            Schema::table('news_comments', function (Blueprint $table) {
                $table->dropIndex('idx_news_comments_user_created');
                $table->dropIndex('idx_news_comments_news_created');
            });
        }

        if (Schema::hasTable('match_comments')) {
            Schema::table('match_comments', function (Blueprint $table) {
                $table->dropIndex('idx_match_comments_user_created');
                $table->dropIndex('idx_match_comments_match_created');
            });
        }
    }

    /**
     * Check if index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }
};