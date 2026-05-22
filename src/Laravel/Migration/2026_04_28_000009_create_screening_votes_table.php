<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('screening_run_id');
            $table->uuid('screening_decision_id')->nullable();
            $table->uuid('project_id');
            $table->uuid('work_id');
            $table->string('stage', 64);
            $table->string('provider', 64);
            $table->string('model', 255);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('decision', 64)->nullable();
            $table->float('confidence')->nullable();
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->json('uncertainty')->nullable();
            $table->json('exclusion_basis')->nullable();
            $table->string('prompt_hash', 64)->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('raw_response')->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('screening_run_id')->references('id')->on('screening_runs')->cascadeOnDelete();
            $table->foreign('screening_decision_id')->references('id')->on('screening_decisions')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->index(['screening_run_id', 'work_id']);
            $table->index(['project_id', 'stage', 'decision']);
            $table->index(['model', 'decision']);
            $table->index(['work_id', 'stage', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_votes');
    }
};
