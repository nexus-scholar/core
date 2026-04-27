<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dedup_clusters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('strategy', 64)->default('default');
            $table->json('thresholds')->nullable();
            // Denormalized for fast "get representative" queries.
            // Must stay in sync with cluster_members.is_representative via repository atomicity.
            $table->uuid('representative_work_id')->nullable();
            // Also denormalized for fast cardinality queries — keep in sync on member mutations.
            $table->unsignedInteger('cluster_size')->default(1);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('representative_work_id')->references('id')->on('scholarly_works')->nullOnDelete();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dedup_clusters');
    }
};

