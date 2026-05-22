<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningRunStatus;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Screening\Domain\ScreeningVote;
use Tests\Support\PersistenceFactory;

it('persists screening runs decisions and model votes through laravel repositories', function (): void {
    $project = PersistenceFactory::makeProject('Scientific Screening Project');
    $workId = (string) Str::uuid();

    ScholarlyWorkModel::create([
        'id' => $workId,
        'title' => 'Tomato instance segmentation with low-label learning',
        'abstract' => 'This paper studies tomato instance segmentation with limited annotations.',
        'year' => 2025,
        'retrieved_at' => now(),
    ]);

    $criteria = ScreeningCriteria::fromArray([
        'include' => ['title_abstract' => 'Include tomato instance segmentation and label-efficient plant vision.'],
        'exclude' => ['title_abstract' => 'Exclude medical, animal, and classification-only papers.'],
    ]);

    $run = ScreeningRun::start(
        id: 'run-1',
        projectId: $project->id,
        stage: ScreeningStage::TITLE_ABSTRACT,
        mode: ScreeningRunMode::LLM_COUNCIL,
        criteria: $criteria,
        name: 'LLM council smoke',
        config: ['models' => ['openai/gpt-4.1-mini', 'google/gemini-2.5-flash']],
        source: ['query_ids' => ['TOM_SEG01']],
    );

    app(ScreeningRunRepositoryPort::class)->start($run);

    $verdict = new ScreeningVerdict(
        id: 'decision-1',
        screeningRunId: $run->id,
        projectId: $project->id,
        workId: $workId,
        stage: ScreeningStage::TITLE_ABSTRACT,
        decision: ScreeningDecision::INCLUDE,
        confidence: 0.91,
        source: 'llm_council',
        rationale: new ScreeningRationale(
            reason: 'Two models included because the abstract directly states tomato instance segmentation with limited annotations.',
            evidence: ['tomato instance segmentation', 'limited annotations'],
        ),
        decidedBy: 'council',
    );

    app(ScreeningDecisionRepositoryPort::class)->record($verdict);

    $vote = ScreeningVote::model(
        id: 'vote-1',
        screeningRunId: $run->id,
        screeningDecisionId: $verdict->id,
        projectId: $project->id,
        workId: $workId,
        stage: ScreeningStage::TITLE_ABSTRACT,
        provider: 'openrouter',
        model: 'openai/gpt-4.1-mini',
        attempt: 1,
        decision: ScreeningDecision::INCLUDE,
        confidence: 0.92,
        rationale: new ScreeningRationale(
            reason: 'Direct match to tomato instance segmentation and limited annotation criteria.',
            evidence: ['tomato instance segmentation'],
        ),
        usage: ['prompt_tokens' => 220, 'completion_tokens' => 80],
        latencyMs: 1500,
    );

    app(ScreeningVoteRepositoryPort::class)->record($vote);
    app(ScreeningRunRepositoryPort::class)->complete($run->id, [
        'total' => 1,
        'included' => 1,
        'needs_review' => 0,
        'excluded' => 0,
        'failed_votes' => 0,
    ]);

    $this->assertDatabaseHas('screening_runs', [
        'id' => 'run-1',
        'project_id' => $project->id,
        'stage' => 'title_abstract',
        'mode' => 'llm_council',
        'status' => 'completed',
        'criteria_hash' => $criteria->hash(),
    ]);

    $this->assertDatabaseHas('screening_decisions', [
        'id' => 'decision-1',
        'screening_run_id' => 'run-1',
        'project_id' => $project->id,
        'work_id' => $workId,
        'stage' => 'title_abstract',
        'decision' => 'include',
        'decision_source' => 'llm_council',
        'included' => true,
        'confidence' => 0.91,
        'decided_by' => 'council',
    ]);

    $this->assertDatabaseHas('screening_votes', [
        'id' => 'vote-1',
        'screening_run_id' => 'run-1',
        'screening_decision_id' => 'decision-1',
        'provider' => 'openrouter',
        'model' => 'openai/gpt-4.1-mini',
        'decision' => 'include',
        'confidence' => 0.92,
        'latency_ms' => 1500,
    ]);

    $latest = app(ScreeningDecisionRepositoryPort::class)
        ->latestForWork($project->id, $workId, ScreeningStage::TITLE_ABSTRACT);

    expect($latest)->not->toBeNull()
        ->and($latest->id)->toBe('decision-1')
        ->and($latest->included())->toBeTrue()
        ->and(DB::table('screening_votes')->where('screening_decision_id', 'decision-1')->count())->toBe(1)
        ->and(DB::table('screening_runs')->where('id', 'run-1')->value('status'))->toBe(ScreeningRunStatus::COMPLETED->value);
});
