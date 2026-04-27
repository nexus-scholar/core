<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cluster_id');
            $table->uuid('work_id');
            $table->boolean('is_representative')->default(false);
            $table->string('reason', 64)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->foreign('cluster_id')->references('id')->on('dedup_clusters')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->unique(['cluster_id', 'work_id']);
            $table->index(['cluster_id', 'is_representative']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_members');
    }
};

