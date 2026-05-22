<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('stage', 64);
            $table->string('name')->nullable();
            $table->string('mode', 64);
            $table->string('status', 64)->default('running');
            $table->string('criteria_hash', 64)->index();
            $table->json('criteria');
            $table->json('config')->nullable();
            $table->json('source')->nullable();
            $table->json('counts')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'stage', 'status']);
            $table->index(['project_id', 'stage', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_runs');
    }
};
