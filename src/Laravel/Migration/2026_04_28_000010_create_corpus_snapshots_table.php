<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corpus_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id');
            $table->timestamp('locked_at');
            $table->unsignedInteger('work_count')->default(0);
            $table->string('created_by')->nullable();
            $table->text('lock_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'locked_at']);
        });

        Schema::create('corpus_snapshot_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('snapshot_id');
            $table->uuid('work_id');
            $table->json('search_query_ids')->nullable();
            $table->json('provider_aliases')->nullable();
            $table->json('provenance')->nullable();
            $table->timestamp('included_at');
            $table->timestamps();

            $table->foreign('snapshot_id')->references('id')->on('corpus_snapshots')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->restrictOnDelete();
            $table->unique(['snapshot_id', 'work_id']);
            $table->index('work_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corpus_snapshot_works');
        Schema::dropIfExists('corpus_snapshots');
    }
};
