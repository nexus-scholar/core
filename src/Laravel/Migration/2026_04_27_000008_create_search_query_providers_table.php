<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_query_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('search_query_id');
            $table->string('provider_alias', 64);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('result_count')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('search_query_id')->references('id')->on('search_queries')->cascadeOnDelete();
            $table->unique(['search_query_id', 'provider_alias']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_query_providers');
    }
};

