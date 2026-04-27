<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('work_id');
            $table->string('stage', 64);
            $table->string('decision', 64);
            $table->text('reason')->nullable();
            $table->string('decided_by', 255)->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->index(['project_id', 'work_id']);
            $table->index(['project_id', 'stage', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_decisions');
    }
};

