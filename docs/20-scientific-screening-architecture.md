# Scientific Screening Architecture

## Decision

LLM screening belongs in `nexus-scholar/core`, not in `nexus-cli`.

`core` should own the scientific screening model, criteria semantics, LLM/council orchestration, persistence ports, Laravel database integration, and comparison logic. `nexus-cli` should only be a thin host that invokes core use cases and exports runtime files for manual inspection.

This is the correct boundary because screening is not a CLI concern. It is a reusable literature-review capability needed by Artisan commands, jobs, HTTP controllers, UI workflows, and future package consumers. Keeping it in the CLI would create a second application layer and force every future host to reimplement the same scientific and persistence rules.

## Goals

Build a screening system that is scientifically auditable, reproducible, and operationally safe:

- tri-state title/abstract decisions: `include`, `needs_review`, `exclude`
- per-paper reasons, evidence snippets, uncertainty flags, and model provenance
- optional model council screening with independent votes from multiple models
- database-backed screening runs, decisions, and votes
- comparison between deterministic/rule screens, single-model screens, council screens, and human adjudication
- reusable application use cases for CLI, jobs, HTTP, and UI
- no Laravel or transport leakage into non-Laravel domain/application code

## Non-Goals

- Do not make the CLI the source of truth.
- Do not store only final binary `included=true/false` decisions.
- Do not let council models see each other's votes during independent voting.
- Do not use hidden reasoning traces as an artifact. Store concise scientific rationale, not chain-of-thought.
- Do not treat LLM output as final systematic-review truth without human adjudication of disagreements and uncertain cases.

## Bounded Context

Add a new `Screening` bounded context:

```text
src/Screening/
  Domain/
  Application/
  Infrastructure/
```

Screening should be separate from `Search` because it is not provider discovery. It consumes works and query provenance, but it produces review decisions. It is also separate from `Deduplication` because screening should normally happen over representative works or deduplicated corpus views while preserving provenance.

## Domain Model

Core concepts:

- `ScreeningRun`: one reproducible screening execution over a corpus.
- `ScreeningCriteria`: versioned title/abstract eligibility rules.
- `ScreeningStage`: `title_abstract`, `full_text`, `human_adjudication`, or custom.
- `ScreeningDecision`: enum-like value object: `include`, `needs_review`, `exclude`.
- `ScreeningVerdict`: final decision for a work in a run.
- `ScreeningVote`: one model or rule engine's independent vote.
- `CouncilVerdict`: aggregate of several independent votes.
- `ScreeningRationale`: reason, evidence, uncertainty, and exclusion basis.
- `ScreeningPolicy`: deterministic aggregation rules for votes and confidence.

Decision semantics:

```text
include
  High-confidence relevant paper based on title/abstract.

needs_review
  Ambiguous, title-only, missing abstract, transfer-method candidate, review/background, or disagreement case.

exclude
  Clearly outside scope based on title/abstract.
```

Compatibility rule:

```text
included = true only when decision === include
included = false for needs_review and exclude
```

This keeps existing downstream PDF retrieval safe while preserving the richer decision state.

## Application Use Cases

Add use cases under `Screening\Application\UseCase`:

- `ScreenWorkHandler`
  - screens one work with configured policy
  - useful for UI, retries, and tests

- `ScreenCorpusHandler`
  - screens many works from a corpus, query, project, or explicit work list
  - records a `ScreeningRun`
  - persists decisions and votes through ports

- `CompareScreeningRunsHandler`
  - compares rule-based, single-model, council, and human runs
  - outputs confusion matrix, disagreement list, and decision transitions

- `AdjudicateScreeningDecisionHandler`
  - records a human decision while preserving the model history

- `ExportScreeningRunHandler`
  - emits JSON/CSV artifacts for manual inspection and CLI compatibility

Application ports:

```php
interface ScreeningRunRepositoryPort;
interface ScreeningDecisionRepositoryPort;
interface ScreeningVoteRepositoryPort;
interface ScreeningWorkSourcePort;
interface LlmClientPort;
interface ScreeningPromptRendererPort;
interface ScreeningComparisonExporterPort;
```

The application layer should depend on these ports, not Eloquent, Guzzle, OpenRouter SDKs, Artisan, or HTTP controllers.

## LLM Provider Design

Add provider-agnostic LLM infrastructure:

```text
src/Screening/Domain/Port/LlmClientPort.php
src/Screening/Infrastructure/Llm/OpenRouterLlmClient.php
src/Screening/Infrastructure/Llm/LlmRequest.php
src/Screening/Infrastructure/Llm/LlmResponse.php
```

OpenRouter should be the first implementation because the current host has `NEXUS_LLM_OPENROUTER_API_KEY`.

The client must:

- use the existing `HttpClientPort` where practical
- support timeout, retry, model id, temperature, max tokens
- request JSON output
- return usage/cost metadata when the provider supplies it
- never log API keys
- preserve raw response in audit metadata only when configured

Recommended defaults verified against OpenRouter model metadata on 2026-05-22:

```env
NEXUS_LLM_SCREENING_MODEL=openai/gpt-4.1-mini
NEXUS_LLM_SCREENING_COUNCIL_MODELS=openai/gpt-4.1-mini,google/gemini-2.5-flash,anthropic/claude-3.5-haiku
NEXUS_LLM_SCREENING_TEMPERATURE=0
NEXUS_LLM_SCREENING_MAX_TOKENS=600
```

Rationale:

- `openai/gpt-4.1-mini`: strong default for structured classification and cost control.
- `google/gemini-2.5-flash`: different model family, useful council diversity.
- `anthropic/claude-3.5-haiku`: different provider family, useful as a third independent vote.
- `openai/gpt-4.1`: optional high-accuracy adjudicator for hard disagreements, not default for every paper.

## Council Screening

Council screening should be configurable:

```php
'screening' => [
    'llm' => [
        'enabled' => env('NEXUS_LLM_SCREENING_ENABLED', true),
        'provider' => env('NEXUS_LLM_PROVIDER', 'openrouter'),
        'model' => env('NEXUS_LLM_SCREENING_MODEL', 'openai/gpt-4.1-mini'),
        'temperature' => env('NEXUS_LLM_SCREENING_TEMPERATURE', 0),
        'max_tokens' => env('NEXUS_LLM_SCREENING_MAX_TOKENS', 600),
        'timeout' => env('NEXUS_LLM_SCREENING_TIMEOUT', 45),
        'store_prompts' => env('NEXUS_LLM_SCREENING_STORE_PROMPTS', false),
        'store_raw_responses' => env('NEXUS_LLM_SCREENING_STORE_RAW_RESPONSES', false),

        'council' => [
            'enabled' => env('NEXUS_LLM_SCREENING_COUNCIL_ENABLED', false),
            'mode' => env('NEXUS_LLM_SCREENING_COUNCIL_MODE', 'uncertain'),
            'models' => explode(',', env(
                'NEXUS_LLM_SCREENING_COUNCIL_MODELS',
                'openai/gpt-4.1-mini,google/gemini-2.5-flash,anthropic/claude-3.5-haiku'
            )),
            'strategy' => env('NEXUS_LLM_SCREENING_COUNCIL_STRATEGY', 'conservative_majority'),
            'confidence_threshold' => env('NEXUS_LLM_SCREENING_COUNCIL_CONFIDENCE_THRESHOLD', 0.75),
            'disagreement_decision' => env('NEXUS_LLM_SCREENING_COUNCIL_DISAGREEMENT_DECISION', 'needs_review'),
        ],
    ],
],
```

Council modes:

- `off`: single model only.
- `always`: every paper gets all configured model votes.
- `uncertain`: council runs only when the primary model returns `needs_review` or confidence is below threshold.
- `disagreement`: council runs when deterministic/rule decision and primary LLM decision conflict.
- `sample`: council runs on a configured random or stratified sample for calibration.

Recommended default:

```text
mode = uncertain
strategy = conservative_majority
```

For a full comparison experiment, use:

```text
mode = always
```

but only after a small smoke test, because 1,926 works with 3 models means 5,778 model calls.

## Council Aggregation

Each model votes independently. The prompt and work metadata are identical for each vote. Models do not see other models' answers.

Aggregation rules:

1. If at least two models choose the same decision and no model chooses the opposite severe decision, use the majority.
2. If decisions split three ways, final decision is `needs_review`.
3. If one model says `include`, one says `exclude`, and one says `needs_review`, final decision is `needs_review`.
4. If two models say `include` and one says `exclude`, final decision is `needs_review` unless both includes have high confidence and cite direct title/abstract evidence.
5. If two models say `exclude` and one says `include`, final decision is `needs_review` unless the include confidence is low or evidence is generic.
6. If a model fails, preserve the failed vote. If the remaining votes agree, use that decision with lower aggregate confidence. If they disagree, use `needs_review`.
7. Missing abstract should bias toward `needs_review` unless title is unambiguous.

This is intentionally conservative. The purpose is to minimize false inclusion and expose uncertain records for human review.

## Prompt Contract

The prompt must be short, stable, and schema-bound.

Inputs:

- review title/project
- screening stage
- inclusion criteria
- exclusion criteria
- query id and query theme
- title
- abstract
- year
- venue
- provider/source
- missing abstract flag

Output schema:

```json
{
  "decision": "include | needs_review | exclude",
  "confidence": 0.0,
  "reason": "One concise sentence.",
  "evidence": ["short title/abstract phrase"],
  "uncertainty": ["missing abstract", "method transfer only"],
  "exclusion_basis": ["classification only", "non-plant domain"]
}
```

Rules:

- Do not ask for chain-of-thought.
- Require evidence to be grounded in title/abstract text.
- If abstract is missing and title is not decisive, return `needs_review`.
- If paper is a review/survey/background source, return `needs_review` unless criteria explicitly include reviews.
- If a method is outside plant/crop/tomato but transferable, return `needs_review`, not `include`.
- If title/abstract clearly conflicts with scope, return `exclude`.

## Database Design

`core` already has `screening_decisions`. Keep it, but evolve it.

### New table: `screening_runs`

Purpose: one reproducible execution.

Suggested columns:

- `id` UUID primary key
- `project_id` UUID indexed
- `stage` string
- `name` string nullable
- `mode` string: `rules`, `llm_single`, `llm_council`, `human`
- `status` string: `running`, `completed`, `failed`, `cancelled`
- `criteria_hash` string indexed
- `criteria` JSON
- `config` JSON
- `source` JSON: query ids, corpus ids, run file, work ids, provider filters
- `counts` JSON
- `started_at`, `completed_at`
- `created_at`, `updated_at`

### Evolve table: `screening_decisions`

Current table fields are usable for audit history, but add:

- `screening_run_id` UUID nullable indexed
- `decision_source` string: `deterministic`, `llm_single`, `llm_council`, `human`, `fallback`
- `confidence` decimal nullable
- `included` boolean indexed
- `criteria_hash` string nullable
- `decision_rank` integer nullable for latest-decision ordering
- `evidence` JSON nullable
- `uncertainty` JSON nullable
- `exclusion_basis` JSON nullable

Keep `metadata` for provider-specific and backward-compatible details.

### New table: `screening_votes`

Purpose: model-level audit trail.

Suggested columns:

- `id` UUID primary key
- `screening_run_id` UUID indexed
- `screening_decision_id` UUID nullable indexed
- `project_id` UUID indexed
- `work_id` UUID indexed
- `stage` string
- `provider` string: `openrouter`
- `model` string
- `attempt` integer
- `decision` string
- `confidence` decimal nullable
- `reason` text nullable
- `evidence` JSON nullable
- `uncertainty` JSON nullable
- `exclusion_basis` JSON nullable
- `prompt_hash` string nullable
- `response_hash` string nullable
- `prompt` long text nullable, config-gated
- `raw_response` long text nullable, config-gated
- `usage` JSON nullable
- `latency_ms` integer nullable
- `error` text nullable
- `created_at`, `updated_at`

Indexes:

- `(screening_run_id, work_id)`
- `(project_id, stage, decision)`
- `(model, decision)`
- `(work_id, stage, created_at)`

### Optional later table: `screening_comparisons`

Can be deferred. Comparison reports can be generated from runs and stored as export artifacts first.

## Persistence Ports

Add:

```php
interface ScreeningRunRepositoryPort
{
    public function start(ScreeningRun $run): void;
    public function complete(ScreeningRunId $id, ScreeningRunCounts $counts): void;
    public function fail(ScreeningRunId $id, string $message): void;
}

interface ScreeningDecisionRepositoryPort
{
    public function record(ScreeningVerdict $verdict): void;
    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict;
}

interface ScreeningVoteRepositoryPort
{
    public function record(ScreeningVote $vote): void;
    /** @return list<ScreeningVote> */
    public function forDecision(ScreeningDecisionId $decisionId): array;
}
```

Laravel implementations live under:

```text
src/Laravel/Persistence/Repository/EloquentScreeningRunRepository.php
src/Laravel/Persistence/Repository/EloquentScreeningDecisionRepository.php
src/Laravel/Persistence/Repository/EloquentScreeningVoteRepository.php
```

## Core vs CLI Boundary

`core` owns:

- criteria parsing and validation
- domain decision semantics
- LLM prompt contract
- OpenRouter client
- council orchestration
- persistence ports and Laravel repositories
- migrations and Eloquent models
- screening run comparison
- Laravel command/job handlers if reusable

`nexus-cli` owns:

- local project command aliases if needed
- user-supplied YAML/query files
- runtime JSON/CSV exports under `storage/screens`
- manual comparison artifacts
- local examples and runbooks

After this change, `nexus-cli` should not contain the scientific screening engine. It should call core.

## Laravel Integration

Register in `NexusServiceProvider`:

- config merge for `nexus.screening`
- `LlmClientPort` -> `OpenRouterLlmClient` when configured
- screening repositories
- `ScreenWorkHandler`
- `ScreenCorpusHandler`
- `CompareScreeningRunsHandler`
- optional reusable `NexusScreenCommand`
- optional `ScreenCorpusJob`

The host app can still provide its own model client by overriding `LlmClientPort`.

## Scientific Workflow

Recommended title/abstract screening workflow:

1. Define criteria.
   - inclusion rules
   - exclusion rules
   - ambiguous-case policy
   - whether reviews are background only or eligible

2. Run deterministic/rule baseline.
   - high precision triage
   - useful as a comparison floor

3. Run single-model LLM on a calibration subset.
   - `--max-works=25` or stratified sample
   - inspect reasons and schema validity

4. Run council mode on calibration subset.
   - compare against rule baseline
   - identify systematic model failure modes

5. Run full LLM/council screen.
   - store run, decisions, votes, costs, failures

6. Generate comparison report.
   - rule vs LLM
   - single vs council
   - disagreements
   - missing-abstract cases
   - domain leakage cases

7. Human adjudication.
   - review all `needs_review`
   - review all rule/LLM conflicts
   - review all model council severe conflicts

8. Lock screening decision set for downstream full-text retrieval.

## Comparison Metrics

For scientific reporting and QA:

- counts by decision and query id
- include/exclude/needs-review confusion matrix
- agreement rate
- Cohen's kappa for two decision sources
- Fleiss' kappa for council votes
- positive agreement for `include`
- negative agreement for `exclude`
- missing abstract decision distribution
- cost and latency per model
- failed-call rate
- top disagreement reasons

If a human gold set exists, add:

- sensitivity/recall for relevant papers
- specificity
- precision
- F1 for include
- false inclusion list
- false exclusion list

For systematic-review safety, false exclusion is the more dangerous error. The default policy should bias ambiguous records to `needs_review`.

## Implementation Plan

### Phase 1: Core screening skeleton

- Add `Screening` bounded context.
- Add value objects for decision, stage, criteria, rationale, vote, verdict.
- Add repository ports.
- Add unit tests for tri-state decisions and council aggregation.

### Phase 2: Database persistence

- Add `screening_runs` migration.
- Add `screening_votes` migration.
- Add migration to extend `screening_decisions`.
- Add Eloquent models and repositories.
- Add feature tests for recording a run, decisions, votes, and latest decision lookup.

### Phase 3: OpenRouter client

- Add `LlmClientPort`.
- Add `OpenRouterLlmClient`.
- Add config and service-provider bindings.
- Add fake-client tests; no live network in CI.
- Add one local smoke command/run path for manual validation.

### Phase 4: LLM and council screening

- Add `ScreenWorkHandler`.
- Add `ScreenCorpusHandler`.
- Add prompt renderer.
- Add single-model tri-state tests.
- Add council mode tests:
  - unanimous include
  - unanimous exclude
  - two-of-three majority
  - severe include/exclude conflict
  - model failure
  - missing abstract

### Phase 5: CLI migration

- Replace `nexus-cli` screening logic with calls to core.
- Keep export files compatible with existing `nexus:fetch-pdfs`.
- Add `--mode=rules|llm|council`, `--stage=title_abstract`, and `--comparison-baseline`.
- Preserve old artifacts under comparison baseline directories.

### Phase 6: Comparison and reporting

- Add comparison use case.
- Add JSON/CSV/Markdown exports.
- Add report for rule-vs-LLM and single-vs-council.

## Testing Strategy

Unit tests:

- decision enum validation
- criteria hash stability
- prompt rendering
- LLM response parser
- evidence and uncertainty normalization
- council aggregation
- no framework imports in `Screening\Domain` or `Screening\Application`

Application tests:

- screen one work with fake model
- screen corpus with fake model
- retries/failures produce persisted failed votes
- missing abstract returns `needs_review` when title is ambiguous
- hard exclusion cannot be overridden silently by LLM unless configured

Feature tests:

- Laravel service provider binds screening ports
- migrations create expected tables and indexes
- repositories persist runs, decisions, votes
- command writes compatible screen output

Regression tests:

- existing PDF retrieval reads only `included=true`
- existing search persistence remains unaffected
- old `screening_decisions` rows remain readable after migration

Manual smoke:

```powershell
php artisan nexus:screen storage\runs\all_20260522_051339.json --mode=llm --max-works=10
php artisan nexus:screen storage\runs\all_20260522_051339.json --mode=council --max-works=10
php artisan nexus:screen:compare --left=rule_v6 --right=llm_smoke
```

## Final Recommendation

Implement this in `core` first. The current `nexus-cli` command should be treated as a prototype and later replaced by a thin adapter.

The best production decision is:

```text
core = reusable screening engine + persistence + LLM/council adapters
nexus-cli = local host, examples, exports, and manual workflows
```

This gives the project a scientific audit trail, database-backed reproducibility, and a clean path to jobs, HTTP, UI, and future non-CLI hosts.
