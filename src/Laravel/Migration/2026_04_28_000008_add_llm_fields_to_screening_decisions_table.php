<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_decisions', function (Blueprint $table) {
            if (! Schema::hasColumn('screening_decisions', 'screening_run_id')) {
                $table->uuid('screening_run_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('screening_decisions', 'decision_source')) {
                $table->string('decision_source', 64)->nullable()->after('decision');
            }
            if (! Schema::hasColumn('screening_decisions', 'confidence')) {
                $table->float('confidence')->nullable()->after('decision_source');
            }
            if (! Schema::hasColumn('screening_decisions', 'included')) {
                $table->boolean('included')->default(false)->after('confidence');
            }
            if (! Schema::hasColumn('screening_decisions', 'criteria_hash')) {
                $table->string('criteria_hash', 64)->nullable()->after('included');
            }
            if (! Schema::hasColumn('screening_decisions', 'decision_rank')) {
                $table->integer('decision_rank')->nullable()->after('criteria_hash');
            }
            if (! Schema::hasColumn('screening_decisions', 'evidence')) {
                $table->json('evidence')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('screening_decisions', 'uncertainty')) {
                $table->json('uncertainty')->nullable()->after('evidence');
            }
            if (! Schema::hasColumn('screening_decisions', 'exclusion_basis')) {
                $table->json('exclusion_basis')->nullable()->after('uncertainty');
            }
        });

        Schema::table('screening_decisions', function (Blueprint $table) {
            $table->foreign('screening_run_id')->references('id')->on('screening_runs')->nullOnDelete();
            $table->index(['screening_run_id', 'work_id']);
            $table->index(['project_id', 'stage', 'included']);
            $table->index(['project_id', 'stage', 'decision_source']);
        });
    }

    public function down(): void
    {
        Schema::table('screening_decisions', function (Blueprint $table) {
            $table->dropForeign(['screening_run_id']);
            $table->dropIndex(['screening_run_id', 'work_id']);
            $table->dropIndex(['project_id', 'stage', 'included']);
            $table->dropIndex(['project_id', 'stage', 'decision_source']);
            $table->dropColumn([
                'screening_run_id',
                'decision_source',
                'confidence',
                'included',
                'criteria_hash',
                'decision_rank',
                'evidence',
                'uncertainty',
                'exclusion_basis',
            ]);
        });
    }
};
