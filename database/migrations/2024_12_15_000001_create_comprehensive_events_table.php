<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Drop the old basic events table if it exists
        Schema::dropIfExists('events');
        
        // Create comprehensive events table
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            
            // Event Classification
            $table->enum('type', [
                'championship', 'tournament', 'scrim', 'qualifier', 
                'regional', 'international', 'invitational', 'community',
                'friendly', 'practice', 'exhibition'
            ])->default('tournament');
            
            $table->enum('tier', ['S', 'A', 'B', 'C'])->default('B');
            
            $table->enum('format', [
                'single_elimination', 'double_elimination', 'round_robin',
                'swiss', 'group_stage', 'bo1', 'bo3', 'bo5'
            ])->default('single_elimination');
            
            $table->string('region', 50);
            $table->string('game_mode', 50);
            
            // Status and Scheduling
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->dateTime('registration_start')->nullable();
            $table->dateTime('registration_end')->nullable();
            $table->string('timezone', 50)->default('UTC');
            
            // Participation
            $table->integer('max_teams')->default(16);
            $table->unsignedBigInteger('organizer_id');
            
            // Prize Pool and Economics
            $table->decimal('prize_pool', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('prize_distribution')->nullable();
            
            // Competition Details
            $table->text('rules')->nullable();
            $table->json('registration_requirements')->nullable();
            $table->json('streams')->nullable();
            $table->json('social_links')->nullable();
            
            // Meta Information
            $table->boolean('featured')->default(false);
            $table->boolean('public')->default(true);
            $table->integer('views')->default(0);
            
            // Bracket and Tournament Data
            $table->json('bracket_data')->nullable();
            $table->json('seeding_data')->nullable();
            $table->integer('current_round')->default(0);
            $table->integer('total_rounds')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'start_date']);
            $table->index(['type', 'region']);
            $table->index(['featured', 'public']);
            $table->index('slug');
            
            // Foreign key
            $table->foreign('organizer_id')->references('id')->on('users')->onDelete('cascade');
        });
        
        // Create event_teams pivot table
        Schema::create('event_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->integer('seed')->nullable();
            $table->enum('status', ['registered', 'confirmed', 'eliminated', 'advanced'])->default('registered');
            $table->dateTime('registered_at');
            $table->json('registration_data')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->unique(['event_id', 'team_id']);
            $table->index(['event_id', 'status']);
        });
        
        // Create brackets table for tournament structure
        Schema::create('brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->enum('bracket_type', ['main', 'upper', 'lower', 'group_a', 'group_b', 'group_c', 'group_d'])->default('main');
            $table->integer('round');
            $table->integer('position');
            $table->string('round_name', 100)->nullable();
            $table->unsignedBigInteger('match_id')->nullable();
            $table->json('bracket_data')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('match_id')->references('id')->on('matches')->onDelete('set null');
            $table->index(['event_id', 'bracket_type', 'round']);
        });
        
        // Create event_standings table for rankings
        Schema::create('event_standings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->integer('position');
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('maps_won')->default(0);
            $table->integer('maps_lost')->default(0);
            $table->decimal('prize_won', 12, 2)->nullable();
            $table->enum('status', ['active', 'eliminated', 'qualified', 'champion'])->default('active');
            $table->json('match_history')->nullable();
            $table->timestamps();
            
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->unique(['event_id', 'team_id']);
            $table->index(['event_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('event_standings');
        Schema::dropIfExists('brackets');
        Schema::dropIfExists('event_teams');
        Schema::dropIfExists('events');
    }
};