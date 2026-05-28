# Core Screening And Adjudication

## Purpose

The Screening context records review decisions over project works. It supports deterministic screening, LLM-assisted screening, model council aggregation, human adjudication, and comparison between screening runs.

Screening is intentionally decision-centric. The package stores the decision, rationale, votes, run state, model metadata, and comparison inputs, but it does not treat automated screening as final scientific truth.

## v1 Status

Status: shipped in v1.0.0.

The v1 package includes:

- tri-state screening decisions,
- screening stages and run modes,
- criteria hashing,
- rationale objects,
- work input objects,
- single-work screening,
- corpus screening,
- LLM client abstraction,
- OpenRouter-backed LLM client,
- disabled LLM fallback,
- prompt rendering,
- council aggregation,
- human adjudication,
- screening-run comparison,
- run, decision, and vote repositories,
- persistence-backed work sources,
- package command support for implemented screening behavior.

## What Shipped

### Decision Model

The screening decision enum has three values:

- `include`,
- `needs_review`,
- `exclude`.

Only `include` is treated as included by `ScreeningDecision::included()`. `needs_review` remains separate from both include and exclude so uncertain automation can be routed to human review.

### Run Model

Screening is organized by:

- `ScreeningStage`,
- `ScreeningRunMode`,
- `ScreeningRunStatus`,
- `ScreeningCriteria`,
- `ScreeningRationale`,
- `ScreeningVerdict`,
- `ScreeningVote`,
- `ScreeningWork`.

Criteria are normalized and hashed for repeatable run identity. Votes preserve provider, model, attempt, confidence, rationale, usage, latency, errors, prompt hash, response hash, prompt storage, and raw response storage when configured.

### Single-Work Screening

`ScreenWorkHandler` screens one `ScreeningWork` with a selected mode. It can:

- return deterministic rule-based behavior,
- call an LLM client,
- parse structured LLM JSON into a verdict,
- record model votes,
- record final decisions,
- fall back to `needs_review` when model execution fails.

### Corpus Screening

`ScreenCorpusHandler` screens works from a repository-backed work source. It requires locked corpus membership, starts a screening run, evaluates each work, records decisions and votes, and completes or fails the run.

The handler can continue after per-work failures depending on command configuration.

### Council Aggregation

`CouncilDecisionAggregator` combines model votes conservatively:

- no successful votes become `needs_review`,
- direct include/exclude conflicts become `needs_review`,
- majority agreement can produce include or exclude,
- failed model attempts lower confidence.

This keeps uncertain automation visible instead of hiding disagreement.

### Human Adjudication

`AdjudicateScreeningDecisionsHandler` records human verdicts for locked project works. It creates or reuses a human adjudication run, persists decisions, and completes the run with adjudication counts.

### Screening Comparison

`CompareScreeningRunsHandler` compares two runs from the same project and stage. It computes:

- agreement count,
- disagreement count,
- transition counts,
- missing baseline works,
- missing candidate works,
- optional per-work rows,
- optional comparison against a human reference run.

## Public API / Commands

The main application entry points are:

- `ScreenWorkHandler`
- `ScreenCorpusHandler`
- `AdjudicateScreeningDecisionsHandler`
- `CompareScreeningRunsHandler`

The main ports are:

- `ScreeningRunRepositoryPort`
- `ScreeningDecisionRepositoryPort`
- `ScreeningVoteRepositoryPort`
- `ScreeningWorkSourcePort`
- `LlmClientPort`
- `ScreeningPromptRendererPort`

The package command `nexus:screen` exposes implemented screening behavior for package consumers. The reusable contract remains the domain objects, handlers, repositories, and LLM/prompt ports.

## Data Model And Persistence

Screening persists through:

- `screening_runs`,
- `screening_decisions`,
- `screening_votes`.

Run records capture project, stage, mode, criteria hash, status, counts, and metadata. Decision records capture work, run, decision, confidence, rationale, source, and LLM metadata. Vote records preserve per-model evidence and failure state.

The persistence-backed work source reads project works from package storage and maps them into `ScreeningWork` inputs for application handlers.

## Main Workflows

### Screen One Work

1. Caller provides a `ScreenWorkCommand`.
2. Handler renders or applies criteria depending on mode.
3. LLM modes call the configured LLM client.
4. Raw model output is parsed and normalized.
5. Votes and final decision are persisted when repositories are configured.
6. A `ScreeningVerdict` is returned.

### Screen A Locked Corpus

1. Caller provides project id, stage, mode, and criteria.
2. Handler verifies the corpus is locked and works are members.
3. A screening run starts.
4. Each work is screened.
5. Counts are updated and the run is completed or failed.

### Adjudicate Decisions

1. Caller provides human verdicts for project works.
2. Handler verifies locked membership.
3. A human run is created or reused.
4. Human decisions are persisted.
5. The run completes with adjudication counts.

### Compare Runs

1. Handler loads the baseline and candidate runs.
2. It verifies project and stage compatibility.
3. Decisions are indexed by work id.
4. Agreement, disagreement, missing data, transitions, and optional rows are returned.

## Validation And Tests

Screening behavior is covered by:

- decision enum tests,
- criteria hash tests,
- rationale and verdict validation tests,
- council aggregation tests,
- single-work handler tests,
- corpus handler tests,
- human adjudication tests,
- run comparison tests,
- prompt renderer tests,
- OpenRouter client tests,
- persistence tests for runs, decisions, votes, and work sources,
- package command feature tests.

Relevant test paths:

- `tests/Unit/Screening`
- `tests/Feature/Persistence/ScreeningPersistenceTest.php`
- `tests/Feature/Persistence/ScreeningWorkSourceTest.php`
- `tests/Feature/Laravel/NexusScreenCommandTest.php`

## What Did Not Ship In v1

The Screening context does not ship:

- a product-facing review queue,
- automatic truth labels,
- benchmark-quality metric reports,
- a persisted comparison report table,
- live LLM calls as part of CI,
- broad provider support beyond the current LLM client abstraction and OpenRouter adapter.

Hosts are responsible for workflow presentation, credential configuration, and any scientific validation protocol around automated screening.

## Changed From Earlier Specs

- Screening is now implemented as a full bounded context with domain, application, infrastructure, persistence, and package command support.
- Council aggregation is conservative and conflict-aware.
- Corpus screening and adjudication require locked project membership.
- LLM support is disabled by default unless explicitly configured.
- Prompt and raw-response storage are configuration controlled.
- Screening comparison returns application results rather than writing a separate persisted comparison entity.

## Implementation References

- Code references:
  - `src/Screening`
  - `src/Laravel/Persistence/Repository/EloquentScreeningRunRepository.php`
  - `src/Laravel/Persistence/Repository/EloquentScreeningDecisionRepository.php`
  - `src/Laravel/Persistence/Repository/EloquentScreeningVoteRepository.php`
  - `src/Laravel/Persistence/EloquentScreeningWorkSource.php`
  - `src/Laravel/Command/NexusScreenCommand.php`
  - `tests/Unit/Screening`
  - `tests/Feature/Persistence`