<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarly_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('title');
            $table->text('abstract')->nullable();
            $table->integer('year')->nullable()->index();
            $table->string('venue_name')->nullable()->index();
            $table->string('venue_issn')->nullable()->index();
            $table->string('venue_type')->nullable(); // journal, conference, preprint, etc.
            $table->text('url')->nullable();
            $table->string('language', 10)->nullable();
            $table->integer('cited_by_count')->default(0);
            $table->boolean('is_retracted')->default(false);
            $table->timestamp('retrieved_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarly_works');
    }
};
