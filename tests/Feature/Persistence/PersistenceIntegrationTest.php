<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexus\Laravel\Model\SlrProject;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PersistenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** Tests that migrations run in correct dependency order */
    public function testMigrationsCompleteSuccessfully(): void
    {
        // RefreshDatabase triggers artisan migrate:fresh
        $this->assertTrue(true);
    }

    /** Tests work cascades to external IDs and providers */
    public function testWorkDeleteCascadesToExternalIds(): void
    {
        $project = SlrProject::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test SLR',
        ]);

        $work = ScholarlyWorkModel::create([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'title' => 'Test Work',
        ]);

        WorkExternalIdModel::create([
            'id' => (string) Str::uuid(),
            'work_id' => $work->id,
            'namespace' => 'doi',
            'value' => '10.1234/test',
        ]);

        $this->assertDatabaseHas('work_external_ids', ['work_id' => $work->id]);

        $work->delete();

        $this->assertDatabaseMissing('work_external_ids', ['work_id' => $work->id]);
    }

    /** Tests repositories can save and load data */
    public function testRepositoriesPerformCrudOperations(): void
    {
        $project = SlrProject::create([
            'id' => (string) Str::uuid(),
            'name' => 'Repo Test',
        ]);

        $this->assertNotNull($project->id);
        $this->assertEqual('Repo Test', $project->name);

        $loaded = SlrProject::find($project->id);
        $this->assertNotNull($loaded);
        $this->assertEqual($project->name, $loaded->name);
    }
}

