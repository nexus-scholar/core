<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 32);
            $table->string('format', 32);
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('project_id')->nullable();
            $table->string('corpus_slice_id')->nullable();
            $table->string('citation_graph_id')->nullable();
            $table->string('requested_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();

            $table->index(['project_id', 'type']);
            $table->index(['type', 'format']);
            $table->index('corpus_slice_id');
            $table->index('citation_graph_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_histories');
    }
};
