<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citation_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('graph_id');
            $table->uuid('citing_work_id');
            $table->uuid('cited_work_id');
            $table->decimal('weight', 8, 4)->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('graph_id')->references('id')->on('citation_graphs')->cascadeOnDelete();
            $table->foreign('citing_work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->foreign('cited_work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->unique(['graph_id', 'citing_work_id', 'cited_work_id']);
            $table->index(['graph_id', 'cited_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citation_edges');
    }
};

