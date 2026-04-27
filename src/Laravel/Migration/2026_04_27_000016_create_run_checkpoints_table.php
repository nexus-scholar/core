<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('graph_id', 255);
            $table->string('run_id', 255);
            $table->string('node_name', 255);
            $table->json('state');
            $table->timestamp('checkpoint_at')->useCurrent();
            $table->timestamps();

            $table->unique(['graph_id', 'run_id', 'node_name']);
            $table->index(['graph_id', 'run_id']);
            $table->index('checkpoint_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_checkpoints');
    }
};

