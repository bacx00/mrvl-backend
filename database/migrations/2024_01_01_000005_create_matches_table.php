<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team1_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('team2_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('set null');
            $table->datetime('scheduled_at');
            $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
            $table->integer('team1_score')->default(0);
            $table->integer('team2_score')->default(0);
            $table->enum('format', ['BO1', 'BO3', 'BO5'])->default('BO3');
            $table->string('current_map')->nullable();
            $table->integer('viewers')->default(0);
            $table->string('stream_url')->nullable();
            $table->json('maps_data')->nullable();
            $table->string('prize_pool')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('matches');
    }
};
