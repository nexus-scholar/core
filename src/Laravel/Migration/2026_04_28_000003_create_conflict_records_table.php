<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('work_id');
            $table->string('stage', 64);
            $table->string('status', 32)->default('unresolved');
            $table->string('resolved_by', 255)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_decision', 64)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_records');
    }
};
