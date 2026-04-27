<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_authors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_id');
            $table->uuid('author_id');
            $table->unsignedInteger('position');
            $table->boolean('is_corresponding')->default(false);
            $table->timestamps();

            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('authors')->cascadeOnDelete();
            $table->unique(['work_id', 'author_id']);
            $table->unique(['work_id', 'position']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_authors');
    }
};

