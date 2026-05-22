<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Application\Prompt\ScreeningPrompt;
use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Screening\Domain\ScreeningVote;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\ValueObject\CorpusOperation;
use Throwable;

final readonly class ScreenWorkHandler
{
    public function __construct(
        private LlmClientPort $llm,
        private ScreeningPromptRendererPort $promptRenderer,
        private CouncilDecisionAggregator $council,
        private ScreeningDecisionRepositoryPort $decisions,
        private ScreeningVoteRepositoryPort $votes,
        private ?CorpusLockPolicy $lockPolicy = null,
    ) {}

    public function handle(ScreenWorkCommand $command): ScreeningVerdict
    {
        $this->lockPolicy?->assertCorpusLocked($command->projectId, CorpusOperation::SCREEN);
        $this->lockPolicy?->assertWorksBelongToProject(
            $command->projectId,
            [$command->work->id],
            CorpusOperation::SCREEN,
        );

        $prompt = $this->promptRenderer->render(
            $command->work,
            $command->criteria,
            $command->stage,
            $command->context,
        );

        $votes = [];

        foreach ($command->models() as $model) {
            $votes[] = $this->modelVote($command, $prompt, $model, 1);
        }

        $verdict = $command->mode() === ScreeningRunMode::LLM_COUNCIL
            ? $this->council->aggregate(
                projectId: $command->projectId,
                workId: $command->work->id,
                stage: $command->stage,
                votes: $votes,
                screeningRunId: $command->screeningRunId,
                criteriaHash: $command->criteria->hash(),
            )
            : $this->singleModelVerdict($command, $votes);

        $this->decisions->record($verdict);

        foreach ($votes as $vote) {
            $this->votes->record($vote->withScreeningDecisionId($verdict->id));
        }

        return $verdict;
    }

    private function modelVote(
        ScreenWorkCommand $command,
        ScreeningPrompt $prompt,
        string $model,
        int $attempt,
    ): ScreeningVote {
        $request = new LlmRequest(
            model: $model,
            messages: $prompt->messages,
            responseSchema: $prompt->responseSchema,
            temperature: $command->temperature,
            maxTokens: $command->maxTokens,
        );

        try {
            $response = $this->llm->completeJson($request);

            return $this->voteFromResponse($command, $prompt, $response, $model, $attempt);
        } catch (Throwable $error) {
            return ScreeningVote::failed(
                screeningRunId: $command->screeningRunId,
                projectId: $command->projectId,
                workId: $command->work->id,
                stage: $command->stage,
                provider: $command->provider,
                model: $model,
                attempt: $attempt,
                error: $error->getMessage(),
                promptHash: $prompt->hash,
                prompt: $command->storePrompt ? $prompt->text() : null,
            );
        }
    }

    private function voteFromResponse(
        ScreenWorkCommand $command,
        ScreeningPrompt $prompt,
        LlmResponse $response,
        string $model,
        int $attempt,
    ): ScreeningVote {
        $content = $response->content;
        $decision = isset($content['decision']) && is_string($content['decision'])
            ? ScreeningDecision::tryFrom($content['decision'])
            : null;

        if (! $decision instanceof ScreeningDecision) {
            throw new \UnexpectedValueException('LLM response did not contain a valid screening decision.');
        }

        $confidence = $content['confidence'] ?? null;
        if (! is_int($confidence) && ! is_float($confidence)) {
            throw new \UnexpectedValueException('LLM response did not contain a numeric confidence.');
        }

        return ScreeningVote::model(
            screeningRunId: $command->screeningRunId,
            projectId: $command->projectId,
            workId: $command->work->id,
            stage: $command->stage,
            provider: $response->provider,
            model: $response->model === '' ? $model : $response->model,
            attempt: $attempt,
            decision: $decision,
            confidence: (float) $confidence,
            rationale: new ScreeningRationale(
                reason: $this->stringField($content, 'reason'),
                evidence: $this->stringListField($content, 'evidence'),
                uncertainty: $this->stringListField($content, 'uncertainty'),
                exclusionBasis: $this->stringListField($content, 'exclusion_basis'),
            ),
            usage: $response->usage,
            latencyMs: $response->latencyMs,
            promptHash: $prompt->hash,
            responseHash: $response->responseHash(),
            prompt: $command->storePrompt ? $prompt->text() : null,
            rawResponse: $command->storeRawResponse ? $response->rawResponse : null,
        );
    }

    /**
     * @param  list<ScreeningVote>  $votes
     */
    private function singleModelVerdict(ScreenWorkCommand $command, array $votes): ScreeningVerdict
    {
        $vote = $votes[0] ?? null;

        if (! $vote instanceof ScreeningVote || ! $vote->succeeded()) {
            return new ScreeningVerdict(
                id: bin2hex(random_bytes(16)),
                screeningRunId: $command->screeningRunId,
                projectId: $command->projectId,
                workId: $command->work->id,
                stage: $command->stage,
                decision: ScreeningDecision::NEEDS_REVIEW,
                confidence: 0.0,
                source: 'llm_single',
                rationale: new ScreeningRationale(
                    reason: 'Single-model screening did not produce a valid decision.',
                    uncertainty: ['model_failure'],
                ),
                decidedBy: $vote?->model,
                decidedAt: new \DateTimeImmutable,
                criteriaHash: $command->criteria->hash(),
                votes: $votes,
            );
        }

        return new ScreeningVerdict(
            id: bin2hex(random_bytes(16)),
            screeningRunId: $command->screeningRunId,
            projectId: $command->projectId,
            workId: $command->work->id,
            stage: $command->stage,
            decision: $vote->decision,
            confidence: $vote->confidence,
            source: 'llm_single',
            rationale: $vote->rationale,
            decidedBy: $vote->model,
            decidedAt: new \DateTimeImmutable,
            criteriaHash: $command->criteria->hash(),
            votes: $votes,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function stringField(array $content, string $key): string
    {
        return isset($content[$key]) && is_scalar($content[$key])
            ? trim((string) $content[$key])
            : '';
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function stringListField(array $content, string $key): array
    {
        $value = $content[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        )));
    }
}
