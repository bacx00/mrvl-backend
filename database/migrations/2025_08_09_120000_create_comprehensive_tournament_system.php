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
        // Upgrade existing tournaments table
        Schema::table('tournaments', function (Blueprint $table) {
            // Add missing columns for comprehensive tournament management
            if (!Schema::hasColumn('tournaments', 'format')) {
                $table->string('format', 50)->default('double_elimination')->after('type');
            }
            if (!Schema::hasColumn('tournaments', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('prize_pool');
            }
            if (!Schema::hasColumn('tournaments', 'max_teams')) {
                $table->integer('max_teams')->default(16)->after('team_count');
            }
            if (!Schema::hasColumn('tournaments', 'min_teams')) {
                $table->integer('min_teams')->default(4)->after('max_teams');
            }
            if (!Schema::hasColumn('tournaments', 'registration_start')) {
                $table->datetime('registration_start')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('tournaments', 'registration_end')) {
                $table->datetime('registration_end')->nullable()->after('registration_start');
            }
            if (!Schema::hasColumn('tournaments', 'check_in_start')) {
                $table->datetime('check_in_start')->nullable()->after('registration_end');
            }
            if (!Schema::hasColumn('tournaments', 'check_in_end')) {
                $table->datetime('check_in_end')->nullable()->after('check_in_start');
            }
            if (!Schema::hasColumn('tournaments', 'rules')) {
                $table->json('rules')->nullable()->after('settings');
            }
            if (!Schema::hasColumn('tournaments', 'timezone')) {
                $table->string('timezone', 50)->default('UTC')->after('rules');
            }
            if (!Schema::hasColumn('tournaments', 'organizer_id')) {
                $table->foreignId('organizer_id')->nullable()->constrained('users')->after('timezone');
            }
            if (!Schema::hasColumn('tournaments', 'logo')) {
                $table->string('logo')->nullable()->after('organizer_id');
            }
            if (!Schema::hasColumn('tournaments', 'banner')) {
                $table->string('banner')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('tournaments', 'featured')) {
                $table->boolean('featured')->default(false)->after('banner');
            }
            if (!Schema::hasColumn('tournaments', 'public')) {
                $table->boolean('public')->default(true)->after('featured');
            }
            if (!Schema::hasColumn('tournaments', 'views')) {
                $table->integer('views')->default(0)->after('public');
            }
            if (!Schema::hasColumn('tournaments', 'current_phase')) {
                $table->string('current_phase', 50)->default('registration')->after('views');
            }
            if (!Schema::hasColumn('tournaments', 'phase_data')) {
                $table->json('phase_data')->nullable()->after('current_phase');
            }
            if (!Schema::hasColumn('tournaments', 'bracket_data')) {
                $table->json('bracket_data')->nullable()->after('phase_data');
            }
            if (!Schema::hasColumn('tournaments', 'seeding_data')) {
                $table->json('seeding_data')->nullable()->after('bracket_data');
            }
            if (!Schema::hasColumn('tournaments', 'qualification_settings')) {
                $table->json('qualification_settings')->nullable()->after('seeding_data');
            }
            if (!Schema::hasColumn('tournaments', 'map_pool')) {
                $table->json('map_pool')->nullable()->after('qualification_settings');
            }
            if (!Schema::hasColumn('tournaments', 'match_format_settings')) {
                $table->json('match_format_settings')->nullable()->after('map_pool');
            }
            if (!Schema::hasColumn('tournaments', 'stream_urls')) {
                $table->json('stream_urls')->nullable()->after('match_format_settings');
            }
            if (!Schema::hasColumn('tournaments', 'discord_url')) {
                $table->string('discord_url')->nullable()->after('stream_urls');
            }
            if (!Schema::hasColumn('tournaments', 'social_links')) {
                $table->json('social_links')->nullable()->after('discord_url');
            }
            if (!Schema::hasColumn('tournaments', 'contact_info')) {
                $table->json('contact_info')->nullable()->after('social_links');
            }
        });

        // Create tournament_phases table
        if (!Schema::hasTable('tournament_phases')) {
            Schema::create('tournament_phases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->index();
                $table->string('phase_type', 50);
                $table->integer('phase_order')->default(1);
                $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');
                $table->text('description')->nullable();
                $table->datetime('start_date')->nullable();
                $table->datetime('end_date')->nullable();
                $table->json('settings')->nullable();
                $table->json('bracket_data')->nullable();
                $table->string('seeding_method', 50)->default('random');
                $table->integer('team_count')->default(0);
                $table->integer('advancement_count')->default(0);
                $table->integer('elimination_count')->default(0);
                $table->string('match_format', 10)->default('bo3');
                $table->json('map_pool')->nullable();
                $table->boolean('is_active')->default(false);
                $table->datetime('completed_at')->nullable();
                $table->json('results_data')->nullable();
                $table->timestamps();
                
                $table->index(['tournament_id', 'phase_order']);
                $table->index(['tournament_id', 'status']);
            });
        }

        // Create tournament_registrations table
        if (!Schema::hasTable('tournament_registrations')) {
            Schema::create('tournament_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained(); // User who registered the team
                $table->enum('status', ['pending', 'approved', 'rejected', 'checked_in', 'disqualified', 'withdrawn'])->default('pending');
                $table->json('registration_data')->nullable();
                $table->datetime('registered_at');
                $table->datetime('checked_in_at')->nullable();
                $table->datetime('approved_at')->nullable();
                $table->datetime('rejected_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->enum('payment_status', ['not_required', 'pending', 'completed', 'failed', 'refunded'])->default('not_required');
                $table->json('payment_data')->nullable();
                $table->text('notes')->nullable();
                $table->json('emergency_contact')->nullable();
                $table->json('special_requirements')->nullable();
                $table->string('submission_ip', 45)->nullable();
                $table->text('approval_notes')->nullable();
                $table->integer('seed')->nullable();
                $table->string('group_assignment', 20)->nullable();
                $table->string('bracket_position', 50)->nullable();
                $table->timestamps();
                
                $table->unique(['tournament_id', 'team_id']);
                $table->index(['tournament_id', 'status']);
                $table->index(['team_id', 'status']);
                $table->index('registered_at');
            });
        }

        // Create tournament_brackets table
        if (!Schema::hasTable('tournament_brackets')) {
            Schema::create('tournament_brackets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tournament_phase_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('bracket_type', 50);
                $table->string('bracket_format', 50)->nullable();
                $table->json('bracket_data')->nullable();
                $table->json('seeding_data')->nullable();
                $table->json('advancement_rules')->nullable();
                $table->json('elimination_rules')->nullable();
                $table->integer('team_count')->default(0);
                $table->integer('round_count')->default(0);
                $table->integer('current_round')->default(1);
                $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'reset'])->default('pending');
                $table->json('position_data')->nullable();
                $table->json('match_settings')->nullable();
                $table->json('tiebreaker_rules')->nullable();
                $table->datetime('completed_at')->nullable();
                $table->json('results_data')->nullable();
                $table->string('group_id', 20)->nullable();
                $table->integer('stage_order')->default(1);
                $table->foreignId('parent_bracket_id')->nullable()->constrained('tournament_brackets')->nullOnDelete();
                $table->boolean('reset_occurred')->default(false);
                $table->timestamps();
                
                $table->index(['tournament_id', 'bracket_type']);
                $table->index(['tournament_phase_id', 'status']);
                $table->index(['tournament_id', 'stage_order']);
            });
        }

        // Upgrade tournament_teams pivot table if it exists, or create it
        if (Schema::hasTable('tournament_teams')) {
            Schema::table('tournament_teams', function (Blueprint $table) {
                // Add missing columns
                $columnsToAdd = [
                    'checked_in_at' => 'datetime',
                    'swiss_buchholz' => 'decimal:8,2',
                    'group_id' => 'string:20',
                    'bracket_position' => 'string:50',
                    'elimination_round' => 'integer',
                    'prize_money' => 'decimal:10,2',
                    'placement' => 'integer',
                    'points_earned' => 'decimal:8,2'
                ];

                foreach ($columnsToAdd as $column => $type) {
                    if (!Schema::hasColumn('tournament_teams', $column)) {
                        switch ($type) {
                            case 'datetime':
                                $table->datetime($column)->nullable();
                                break;
                            case 'decimal:8,2':
                                $table->decimal($column, 8, 2)->default(0.0);
                                break;
                            case 'decimal:10,2':
                                $table->decimal($column, 10, 2)->nullable();
                                break;
                            case 'integer':
                                $table->integer($column)->nullable();
                                break;
                            default:
                                $table->string($column, 50)->nullable();
                        }
                    }
                }
                
                // Ensure status column exists with proper enum
                if (!Schema::hasColumn('tournament_teams', 'status')) {
                    $table->enum('status', ['registered', 'checked_in', 'disqualified', 'advanced', 'eliminated'])->default('registered');
                }
            });
        } else {
            Schema::create('tournament_teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->integer('seed')->nullable();
                $table->enum('status', ['registered', 'checked_in', 'disqualified', 'advanced', 'eliminated'])->default('registered');
                $table->datetime('registered_at');
                $table->datetime('checked_in_at')->nullable();
                $table->integer('swiss_wins')->default(0);
                $table->integer('swiss_losses')->default(0);
                $table->decimal('swiss_score', 8, 2)->default(0.0);
                $table->decimal('swiss_buchholz', 8, 2)->default(0.0);
                $table->string('group_id', 20)->nullable();
                $table->string('bracket_position', 50)->nullable();
                $table->integer('elimination_round')->nullable();
                $table->decimal('prize_money', 10, 2)->nullable();
                $table->integer('placement')->nullable();
                $table->decimal('points_earned', 8, 2)->nullable();
                $table->timestamps();
                
                $table->unique(['tournament_id', 'team_id']);
                $table->index(['tournament_id', 'status']);
                $table->index(['tournament_id', 'swiss_score']);
            });
        }

        // Update bracket_matches table if it exists
        if (Schema::hasTable('bracket_matches')) {
            Schema::table('bracket_matches', function (Blueprint $table) {
                $columnsToAdd = [
                    'tournament_bracket_id' => 'foreignId',
                    'tournament_phase_id' => 'foreignId',
                    'match_identifier' => 'string:50',
                    'match_number' => 'integer',
                    'match_format' => 'string:10',
                    'scheduled_at' => 'datetime',
                    'map_data' => 'json',
                    'veto_data' => 'json',
                    'is_walkover' => 'boolean',
                    'walkover_reason' => 'text',
                    'referee_id' => 'foreignId',
                    'stream_url' => 'string',
                    'broadcast_data' => 'json',
                    'statistics' => 'json'
                ];

                foreach ($columnsToAdd as $column => $type) {
                    if (!Schema::hasColumn('bracket_matches', $column)) {
                        switch ($type) {
                            case 'foreignId':
                                if ($column === 'tournament_bracket_id') {
                                    $table->foreignId($column)->nullable()->constrained('tournament_brackets')->nullOnDelete();
                                } elseif ($column === 'tournament_phase_id') {
                                    $table->foreignId($column)->nullable()->constrained('tournament_phases')->nullOnDelete();
                                } elseif ($column === 'referee_id') {
                                    $table->foreignId($column)->nullable()->constrained('users')->nullOnDelete();
                                }
                                break;
                            case 'datetime':
                                $table->datetime($column)->nullable();
                                break;
                            case 'json':
                                $table->json($column)->nullable();
                                break;
                            case 'boolean':
                                $table->boolean($column)->default(false);
                                break;
                            case 'text':
                                $table->text($column)->nullable();
                                break;
                            case 'integer':
                                $table->integer($column)->nullable();
                                break;
                            case 'string':
                                $table->string($column)->nullable();
                                break;
                            case 'string:10':
                                $table->string($column, 10)->default('bo3');
                                break;
                            case 'string:50':
                                $table->string($column, 50)->nullable();
                                break;
                        }
                    }
                }

                // Add indexes
                if (!$this->hasIndex('bracket_matches', 'bracket_matches_tournament_bracket_id_index')) {
                    $table->index('tournament_bracket_id');
                }
                if (!$this->hasIndex('bracket_matches', 'bracket_matches_tournament_phase_id_index')) {
                    $table->index('tournament_phase_id');
                }
                if (!$this->hasIndex('bracket_matches', 'bracket_matches_match_identifier_index')) {
                    $table->index('match_identifier');
                }
                if (!$this->hasIndex('bracket_matches', 'bracket_matches_scheduled_at_index')) {
                    $table->index('scheduled_at');
                }
            });
        }

        // Update tournaments table enum values to match our constants
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN type ENUM('mrc', 'mri', 'ignite', 'community', 'qualifier', 'regional', 'international', 'showmatch', 'scrim') DEFAULT 'community'");
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN format ENUM('single_elimination', 'double_elimination', 'swiss', 'round_robin', 'group_stage_playoffs', 'ladder') DEFAULT 'double_elimination'");
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN status ENUM('draft', 'registration_open', 'registration_closed', 'check_in', 'ongoing', 'completed', 'cancelled', 'postponed') DEFAULT 'draft'");
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN current_phase ENUM('registration', 'check_in', 'open_qualifier_1', 'open_qualifier_2', 'closed_qualifier', 'group_stage', 'swiss_rounds', 'upper_bracket', 'lower_bracket', 'playoffs', 'grand_final', 'completed') DEFAULT 'registration'");
        
        // Create indexes for performance
        Schema::table('tournaments', function (Blueprint $table) {
            if (!$this->hasIndex('tournaments', 'tournaments_type_status_index')) {
                $table->index(['type', 'status']);
            }
            if (!$this->hasIndex('tournaments', 'tournaments_format_region_index')) {
                $table->index(['format', 'region']);
            }
            if (!$this->hasIndex('tournaments', 'tournaments_featured_public_index')) {
                $table->index(['featured', 'public']);
            }
            if (!$this->hasIndex('tournaments', 'tournaments_registration_dates_index')) {
                $table->index(['registration_start', 'registration_end']);
            }
            if (!$this->hasIndex('tournaments', 'tournaments_tournament_dates_index')) {
                $table->index(['start_date', 'end_date']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('tournament_brackets');
        Schema::dropIfExists('tournament_registrations');
        Schema::dropIfExists('tournament_phases');
        
        // Remove added columns from tournaments table
        Schema::table('tournaments', function (Blueprint $table) {
            $columnsToRemove = [
                'format', 'currency', 'max_teams', 'min_teams', 'registration_start', 
                'registration_end', 'check_in_start', 'check_in_end', 'rules', 'timezone',
                'organizer_id', 'logo', 'banner', 'featured', 'public', 'views',
                'current_phase', 'phase_data', 'bracket_data', 'seeding_data',
                'qualification_settings', 'map_pool', 'match_format_settings',
                'stream_urls', 'discord_url', 'social_links', 'contact_info'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('tournaments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // Remove added columns from bracket_matches table
        if (Schema::hasTable('bracket_matches')) {
            Schema::table('bracket_matches', function (Blueprint $table) {
                $columnsToRemove = [
                    'tournament_bracket_id', 'tournament_phase_id', 'match_identifier',
                    'match_number', 'match_format', 'scheduled_at', 'map_data',
                    'veto_data', 'is_walkover', 'walkover_reason', 'referee_id',
                    'stream_url', 'broadcast_data', 'statistics'
                ];

                foreach ($columnsToRemove as $column) {
                    if (Schema::hasColumn('bracket_matches', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // Remove added columns from tournament_teams pivot table
        if (Schema::hasTable('tournament_teams')) {
            Schema::table('tournament_teams', function (Blueprint $table) {
                $columnsToRemove = [
                    'checked_in_at', 'swiss_buchholz', 'group_id', 'bracket_position',
                    'elimination_round', 'prize_money', 'placement', 'points_earned'
                ];

                foreach ($columnsToRemove as $column) {
                    if (Schema::hasColumn('tournament_teams', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Check if index exists
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $index) {
            if ($index->Key_name === $indexName) {
                return true;
            }
        }
        return false;
    }
};