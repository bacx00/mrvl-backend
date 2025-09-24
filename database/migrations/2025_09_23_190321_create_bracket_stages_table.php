<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bracket_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('tournament_id')->nullable();
            $table->string('name');
            $table->string('type'); // single_elimination, double_elimination, swiss, round_robin, group_stage, gsl
            $table->integer('stage_order');
            $table->enum('status', ['pending', 'ready', 'active', 'completed', 'cancelled'])->default('pending');
            $table->json('settings')->nullable();
            $table->integer('max_teams')->nullable();
            $table->integer('current_round')->default(0);
            $table->integer('total_rounds')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->index(['event_id', 'stage_order']);
            $table->index('status');
        });

        // Add columns to matches table for stage support
        Schema::table('matches', function (Blueprint $table) {
            if (!Schema::hasColumn('matches', 'stage_id')) {
                $table->unsignedBigInteger('stage_id')->nullable()->after('event_id');
            }
            if (!Schema::hasColumn('matches', 'bracket_type')) {
                $table->string('bracket_type')->nullable()->after('stage_id');
            }
            if (!Schema::hasColumn('matches', 'round_name')) {
                $table->string('round_name')->nullable()->after('round_number');
            }
            if (!Schema::hasColumn('matches', 'match_number')) {
                $table->integer('match_number')->nullable()->after('round_name');
            }
            if (!Schema::hasColumn('matches', 'group_name')) {
                $table->string('group_name')->nullable();
            }
            if (!Schema::hasColumn('matches', 'team1_placeholder')) {
                $table->string('team1_placeholder')->nullable();
            }
            if (!Schema::hasColumn('matches', 'team2_placeholder')) {
                $table->string('team2_placeholder')->nullable();
            }
            if (!Schema::hasColumn('matches', 'best_of')) {
                $table->integer('best_of')->default(3);
            }
            if (!Schema::hasColumn('matches', 'match_format')) {
                $table->string('match_format')->default('Bo3');
            }
            if (!Schema::hasColumn('matches', 'is_grand_final')) {
                $table->boolean('is_grand_final')->default(false);
            }
            if (!Schema::hasColumn('matches', 'is_bracket_reset')) {
                $table->boolean('is_bracket_reset')->default(false);
            }
            if (!Schema::hasColumn('matches', 'is_third_place')) {
                $table->boolean('is_third_place')->default(false);
            }
        });

        // Add tournament_structure column to events table
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'tournament_structure')) {
                $table->json('tournament_structure')->nullable();
            }
            if (!Schema::hasColumn('events', 'bracket_generated_at')) {
                $table->timestamp('bracket_generated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'tournament_structure')) {
                $table->dropColumn('tournament_structure');
            }
            if (Schema::hasColumn('events', 'bracket_generated_at')) {
                $table->dropColumn('bracket_generated_at');
            }
        });

        Schema::table('matches', function (Blueprint $table) {
            $columnsToRemove = [
                'stage_id', 'bracket_type', 'round_name', 'match_number',
                'group_name', 'team1_placeholder', 'team2_placeholder',
                'best_of', 'match_format', 'is_grand_final',
                'is_bracket_reset', 'is_third_place'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('matches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('bracket_stages');
    }
};
