<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_fetches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_id');
            $table->string('source_alias', 64);
            $table->text('source_url')->nullable();
            $table->string('status', 32);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('work_id')->references('id')->on('scholarly_works')->cascadeOnDelete();
            $table->index(['work_id', 'attempted_at']);
            $table->index(['work_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_fetches');
    }
};

