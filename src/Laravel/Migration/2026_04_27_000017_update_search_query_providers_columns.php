<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_query_providers', function (Blueprint $table) {
            if (!Schema::hasColumn('search_query_providers', 'total_raw')) {
                $table->unsignedInteger('total_raw')->default(0);
            }
            if (!Schema::hasColumn('search_query_providers', 'total_unique')) {
                $table->unsignedInteger('total_unique')->default(0);
            }
            if (!Schema::hasColumn('search_query_providers', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->default(0);
            }
            if (!Schema::hasColumn('search_query_providers', 'error_message')) {
                $table->text('error_message')->nullable();
            }
            if (!Schema::hasColumn('search_query_providers', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('search_query_providers', function (Blueprint $table) {
            $table->dropColumn(['total_raw', 'total_unique', 'duration_ms', 'error_message', 'metadata']);
        });
    }
};
