<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['International', 'Regional', 'Qualifier', 'Community'])->default('Tournament');
            $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('prize_pool')->nullable();
            $table->integer('team_count')->default(32);
            $table->string('location')->nullable();
            $table->string('organizer')->nullable();
            $table->string('format')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('registration_open')->default(false);
            $table->integer('stream_viewers')->default(0);
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('events');
    }
};
