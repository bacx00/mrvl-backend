<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatusMonitoringTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Status incidents table
        Schema::create('status_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message')->nullable();
            $table->enum('status', ['investigating', 'identified', 'monitoring', 'resolved']);
            $table->enum('severity', ['maintenance', 'minor', 'major', 'critical']);
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->json('affected_services')->nullable();
            $table->json('updates')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // API metrics table
        Schema::create('api_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('endpoint');
            $table->integer('response_time'); // in ms
            $table->integer('status_code');
            $table->string('method', 10);
            $table->timestamps();
            $table->index(['service', 'created_at']);
            $table->index('created_at');
        });

        // Maintenance schedule table
        Schema::create('maintenance_schedule', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->timestamp('scheduled_start');
            $table->timestamp('scheduled_end');
            $table->json('affected_services');
            $table->enum('impact', ['none', 'minor', 'major', 'critical']);
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled']);
            $table->timestamps();
        });

        // Issue reports table
        Schema::create('issue_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('service');
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('contact_email')->nullable();
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed']);
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['status', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('issue_reports');
        Schema::dropIfExists('maintenance_schedule');
        Schema::dropIfExists('api_metrics');
        Schema::dropIfExists('status_incidents');
    }
}