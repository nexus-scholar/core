<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citation_graphs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->string('name')->nullable();
            // Must be one of: 'direct_citation', 'co_citation', 'bibliographic_coupling'
            // Enforced via Eloquent cast. See CitationGraphType domain enum.
            $table->string('graph_type', 64);
            $table->unsignedInteger('node_count')->default(0);
            $table->unsignedInteger('edge_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'graph_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citation_graphs');
    }
};

