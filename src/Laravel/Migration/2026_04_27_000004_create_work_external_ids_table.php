<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_external_ids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_id');
            $table->string('namespace', 32);
            $table->string('value', 512);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            // Unique per work — a DOI may be stored in multiple works across projects
            $table->unique(['work_id', 'namespace', 'value']);
            $table->index(['work_id', 'namespace']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_external_ids');
    }
};

