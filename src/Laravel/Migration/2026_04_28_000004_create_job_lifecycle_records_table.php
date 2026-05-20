<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_lifecycle_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 64)->unique();
            $table->string('run_id', 255);
            $table->string('job_name', 64);
            $table->string('job_class', 255);
            $table->string('status', 32);
            $table->string('project_id', 255)->nullable();
            $table->string('work_id', 255)->nullable();
            $table->json('context')->nullable();
            $table->json('summary')->nullable();
            $table->string('error_class', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['run_id', 'status']);
            $table->index(['job_name', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['work_id', 'status']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_lifecycle_records');
    }
};
