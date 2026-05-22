<?php

declare(strict_types=1);

namespace Nexus\Screening\Domain;

final class CouncilDecisionAggregator
{
    /**
     * @param  list<ScreeningVote>  $votes
     */
    public function aggregate(
        string $projectId,
        string $workId,
        ScreeningStage $stage,
        array $votes,
        ?string $screeningRunId = null,
        ?string $criteriaHash = null,
    ): ScreeningVerdict {
        $successful = array_values(array_filter(
            $votes,
            static fn (ScreeningVote $vote): bool => $vote->succeeded(),
        ));

        $failed = array_values(array_filter(
            $votes,
            static fn (ScreeningVote $vote): bool => ! $vote->succeeded(),
        ));

        if ($successful === []) {
            return $this->verdict(
                projectId: $projectId,
                workId: $workId,
                stage: $stage,
                decision: ScreeningDecision::NEEDS_REVIEW,
                confidence: 0.0,
                reason: 'Council could not produce a valid model vote.',
                votes: $votes,
                screeningRunId: $screeningRunId,
                criteriaHash: $criteriaHash,
                uncertainty: $this->failedUncertainty($failed),
            );
        }

        $hasInclude = $this->hasDecision($successful, ScreeningDecision::INCLUDE);
        $hasExclude = $this->hasDecision($successful, ScreeningDecision::EXCLUDE);

        if ($hasInclude && $hasExclude) {
            return $this->verdict(
                projectId: $projectId,
                workId: $workId,
                stage: $stage,
                decision: ScreeningDecision::NEEDS_REVIEW,
                confidence: $this->averageConfidence($successful) * 0.75,
                reason: 'Council disagreement: include and exclude votes require manual review.',
                votes: $votes,
                screeningRunId: $screeningRunId,
                criteriaHash: $criteriaHash,
                uncertainty: array_merge(['council_include_exclude_conflict'], $this->failedUncertainty($failed)),
            );
        }

        $counts = $this->decisionCounts($successful);
        arsort($counts);

        $decisionValue = array_key_first($counts);
        $decision = ScreeningDecision::from((string) $decisionValue);
        $winningCount = (int) $counts[$decision->value];
        $totalVotes = count($votes);

        if ($winningCount < 2 && count($successful) > 1) {
            return $this->verdict(
                projectId: $projectId,
                workId: $workId,
                stage: $stage,
                decision: ScreeningDecision::NEEDS_REVIEW,
                confidence: $this->averageConfidence($successful) * 0.75,
                reason: 'Council split did not produce a stable majority.',
                votes: $votes,
                screeningRunId: $screeningRunId,
                criteriaHash: $criteriaHash,
                uncertainty: array_merge(['council_split_vote'], $this->failedUncertainty($failed)),
            );
        }

        $confidence = $this->averageConfidence(array_values(array_filter(
            $successful,
            static fn (ScreeningVote $vote): bool => $vote->decision === $decision,
        )));

        if ($failed !== []) {
            $confidence *= 0.95;
        }

        return $this->verdict(
            projectId: $projectId,
            workId: $workId,
            stage: $stage,
            decision: $decision,
            confidence: $confidence,
            reason: "Council majority: {$winningCount} of {$totalVotes} models voted {$decision->value}.",
            votes: $votes,
            screeningRunId: $screeningRunId,
            criteriaHash: $criteriaHash,
            uncertainty: $this->failedUncertainty($failed),
        );
    }

    /**
     * @param  list<ScreeningVote>  $votes
     */
    private function verdict(
        string $projectId,
        string $workId,
        ScreeningStage $stage,
        ScreeningDecision $decision,
        float $confidence,
        string $reason,
        array $votes,
        ?string $screeningRunId,
        ?string $criteriaHash,
        array $uncertainty = [],
    ): ScreeningVerdict {
        return new ScreeningVerdict(
            id: bin2hex(random_bytes(16)),
            screeningRunId: $screeningRunId,
            projectId: $projectId,
            workId: $workId,
            stage: $stage,
            decision: $decision,
            confidence: max(0.0, min(1.0, $confidence)),
            source: 'llm_council',
            rationale: new ScreeningRationale(
                reason: $reason,
                evidence: $this->collectEvidence($votes),
                uncertainty: array_values(array_unique($uncertainty)),
                exclusionBasis: $this->collectExclusionBasis($votes),
            ),
            decidedBy: 'council',
            decidedAt: new \DateTimeImmutable,
            criteriaHash: $criteriaHash,
            votes: $votes,
        );
    }

    /**
     * @param  list<ScreeningVote>  $votes
     */
    private function hasDecision(array $votes, ScreeningDecision $decision): bool
    {
        foreach ($votes as $vote) {
            if ($vote->decision === $decision) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ScreeningVote>  $votes
     * @return array<string, int>
     */
    private function decisionCounts(array $votes): array
    {
        $counts = [];

        foreach ($votes as $vote) {
            if (! $vote->decision instanceof ScreeningDecision) {
                continue;
            }

            $counts[$vote->decision->value] = ($counts[$vote->decision->value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<ScreeningVote>  $votes
     */
    private function averageConfidence(array $votes): float
    {
        $values = array_values(array_filter(
            array_map(static fn (ScreeningVote $vote): ?float => $vote->confidence, $votes),
            static fn (?float $confidence): bool => $confidence !== null,
        ));

        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  list<ScreeningVote>  $votes
     * @return list<string>
     */
    private function collectEvidence(array $votes): array
    {
        $evidence = [];

        foreach ($votes as $vote) {
            array_push($evidence, ...$vote->rationale->evidence);
        }

        return array_values(array_unique(array_filter($evidence)));
    }

    /**
     * @param  list<ScreeningVote>  $votes
     * @return list<string>
     */
    private function collectExclusionBasis(array $votes): array
    {
        $basis = [];

        foreach ($votes as $vote) {
            array_push($basis, ...$vote->rationale->exclusionBasis);
        }

        return array_values(array_unique(array_filter($basis)));
    }

    /**
     * @param  list<ScreeningVote>  $failed
     * @return list<string>
     */
    private function failedUncertainty(array $failed): array
    {
        return array_values(array_unique(array_map(
            static fn (ScreeningVote $vote): string => "model_failure:{$vote->model}",
            $failed,
        )));
    }
}
