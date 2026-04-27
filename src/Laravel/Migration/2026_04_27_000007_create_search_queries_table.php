<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->text('query_text');
            $table->integer('from_year')->nullable();
            $table->integer('to_year')->nullable();
            $table->string('language', 10)->nullable();
            $table->unsignedInteger('max_results')->default(100);
            $table->unsignedInteger('offset')->default(0);
            $table->boolean('include_raw_data')->default(false);
            $table->json('provider_aliases')->nullable();
            $table->char('cache_key', 64)->index();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('total_raw')->default(0);
            $table->unsignedInteger('total_unique')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};

