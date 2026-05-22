<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Tests\Support\PersistenceFactory;

it('screens persisted project works through the reusable corpus handler', function (): void {
    $project = PersistenceFactory::makeProject('Screen Command Project');
    $query = SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'query_text' => 'tomato instance segmentation',
        'max_results' => 5,
        'cache_key' => hash('sha256', $project->id.'tomato'),
        'status' => 'completed',
    ]);
    $work = ScholarlyWorkModel::create([
        'id' => (string) Str::uuid(),
        'title' => 'Tomato instance segmentation with sparse labels',
        'abstract' => 'A tomato instance segmentation paper with sparse labels.',
        'year' => 2025,
        'venue_name' => 'Plant Methods',
        'retrieved_at' => now(),
    ]);

    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $query->id,
        'work_id' => $work->id,
        'provider_alias' => 'openalex',
        'provider_work_id' => 'W1',
        'rank' => 1,
    ]);

    app()->instance(LlmClientPort::class, new NexusScreenCommandFakeLlmClient([
        'decision' => 'include',
        'confidence' => 0.94,
        'reason' => 'Direct match to tomato instance segmentation and sparse labels.',
        'evidence' => ['Tomato instance segmentation', 'sparse labels'],
        'uncertainty' => [],
        'exclusion_basis' => [],
    ]));
    app(ProjectLockLifecyclePort::class)->lock($project->id, actorId: 'test-reviewer', reason: 'screening fixture');

    $this->artisan('nexus:screen', [
        '--project' => $project->id,
        '--include' => ['tomato instance segmentation', 'label-efficient crop vision'],
        '--exclude' => ['medical imaging', 'classification only'],
        '--model' => 'openai/gpt-4.1-mini',
        '--max' => 1,
        '--name' => 'command smoke',
    ])
        ->expectsOutputToContain('Screening complete.')
        ->assertExitCode(0);

    expect(DB::table('screening_runs')->where('project_id', $project->id)->value('name'))->toBe('command smoke')
        ->and(DB::table('screening_decisions')->where('work_id', $work->id)->value('decision'))->toBe('include')
        ->and(DB::table('screening_votes')->where('work_id', $work->id)->value('model'))->toBe('openai/gpt-4.1-mini');
});

final class NexusScreenCommandFakeLlmClient implements LlmClientPort
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(private readonly array $content) {}

    public function completeJson(LlmRequest $request): LlmResponse
    {
        return new LlmResponse(
            provider: 'openrouter',
            model: $request->model,
            content: $this->content,
            rawResponse: json_encode($this->content, JSON_THROW_ON_ERROR),
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 40],
            latencyMs: 25,
        );
    }
}
