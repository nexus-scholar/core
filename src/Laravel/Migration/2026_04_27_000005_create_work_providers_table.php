<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_id');
            $table->string('provider_alias', 64);
            $table->string('provider_work_id', 255)->nullable();
            // metadata: provider-specific metadata only (e.g. indexing hints, dedup confidence).
            // Raw provider payloads are opt-in only via SearchQuery::includeRawData flag.
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->unique(['work_id', 'provider_alias']);
            $table->index(['provider_alias', 'provider_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_providers');
    }
};

