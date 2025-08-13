<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePerformanceMetricsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->json('metrics');
            $table->timestamps();
            
            // Index for time-based queries
            $table->index('created_at');
        });
        
        // Create API logs table for tracking API performance
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->integer('status_code');
            $table->float('response_time');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            
            // Indexes for performance queries
            $table->index(['endpoint', 'created_at']);
            $table->index('created_at');
            $table->index('user_id');
        });
        
        // Create query log table for slow query tracking
        Schema::create('query_log', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->float('execution_time');
            $table->string('connection', 50);
            $table->timestamps();
            
            // Index for performance queries
            $table->index(['execution_time', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('query_log');
    }
}