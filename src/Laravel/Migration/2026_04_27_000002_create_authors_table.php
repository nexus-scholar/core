<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->index();
            $table->string('normalized_name')->index(); // lowercase, no diacritics
            $table->string('orcid')->nullable()->unique();
            $table->string('scopus_id')->nullable()->unique();
            $table->string('openalex_id')->nullable()->unique();
            $table->string('s2_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
