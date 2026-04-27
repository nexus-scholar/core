<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('search_query_id');
            $table->uuid('work_id');
            $table->string('provider_alias', 64)->nullable();
            $table->string('provider_work_id', 255)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->timestamp('seen_at')->useCurrent();
            $table->timestamps();

            $table->foreign('search_query_id')->references('id')->on('search_queries')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->unique(['search_query_id', 'work_id']);
            $table->index(['provider_alias', 'provider_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_works');
    }
};

