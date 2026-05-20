<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'locked_by')) {
                $table->string('locked_by')->nullable()->after('locked_at');
            }
            if (!Schema::hasColumn('projects', 'lock_reason')) {
                $table->text('lock_reason')->nullable()->after('locked_by');
            }
            if (!Schema::hasColumn('projects', 'unlocked_at')) {
                $table->timestamp('unlocked_at')->nullable()->after('lock_reason');
            }
            if (!Schema::hasColumn('projects', 'unlocked_by')) {
                $table->string('unlocked_by')->nullable()->after('unlocked_at');
            }
            if (!Schema::hasColumn('projects', 'unlock_reason')) {
                $table->text('unlock_reason')->nullable()->after('unlocked_by');
            }
        });

        Schema::create('project_lock_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id');
            $table->string('action', 32);
            $table->string('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['project_id', 'action']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_lock_audits');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'locked_by',
                'lock_reason',
                'unlocked_at',
                'unlocked_by',
                'unlock_reason',
            ]);
        });
    }
};
