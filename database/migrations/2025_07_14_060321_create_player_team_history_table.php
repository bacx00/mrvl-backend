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
        Schema::create('player_team_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->onDelete('set null');
            $table->timestamp('change_date');
            $table->enum('change_type', ['joined', 'left', 'transferred', 'released', 'retired', 'loan_start', 'loan_end']);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('transfer_fee', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_official')->default(true);
            $table->string('source_url')->nullable();
            $table->foreignId('announced_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['player_id', 'change_date']);
            $table->index('change_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_team_history');
    }
};
