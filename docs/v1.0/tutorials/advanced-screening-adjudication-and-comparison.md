# Advanced Screening, Human Adjudication, And Run Comparison

This tutorial builds the review-quality screening path on top of `nexus-scholar/core`.

You will wire three host commands:

- `review:screen-project` for LLM or council screening,
- `review:adjudicate` for human decisions,
- `review:compare-screening` for run-to-run agreement analysis.

This path assumes the project corpus has already been locked. Core requires locked corpus membership for screening and adjudication so decisions are made against stable project membership.

## Configure LLM Screening

In `.env`:

```dotenv
NEXUS_LLM_SCREENING_ENABLED=true
NEXUS_LLM_PROVIDER=openrouter
NEXUS_LLM_OPENROUTER_API_KEY=
NEXUS_LLM_SCREENING_MODEL=openai/gpt-4.1-mini
NEXUS_LLM_SCREENING_COUNCIL_ENABLED=true
NEXUS_LLM_SCREENING_COUNCIL_MODELS=openai/gpt-4.1-mini,google/gemini-2.5-flash,mistralai/mistral-small-2603
```

Then clear config:

```powershell
php artisan config:clear
```

If LLM screening is disabled or fails, core keeps the workflow auditable by returning `needs_review` instead of pretending a confident model decision exists.

## Build Project Screening

```powershell
php artisan make:command ReviewScreenProject
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Screening\Application\UseCase\ScreenCorpusCommand;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;

class ReviewScreenProject extends Command
{
    protected $signature = 'review:screen-project
        {--project= : Locked project ID}
        {--include=* : Inclusion criterion}
        {--exclude=* : Exclusion criterion}
        {--mode=llm_single : llm_single or llm_council}
        {--model= : Single model ID}
        {--council-model=* : Council model ID, repeatable}
        {--limit= : Max works}
        {--work-id=* : Internal or namespaced work IDs}
        {--query-id=* : Search query IDs}
        {--name= : Run name}
        {--store-prompts : Persist rendered prompts}
        {--store-raw-responses : Persist raw model responses}';

    public function handle(ScreenCorpusHandler $screen): int
    {
        $projectId = $this->requiredString('project');
        if ($projectId === null) {
            return self::FAILURE;
        }

        $criteria = ScreeningCriteria::fromArray([
            'include' => array_values((array) $this->option('include')),
            'exclude' => array_values((array) $this->option('exclude')),
        ]);

        $result = $screen->handle(new ScreenCorpusCommand(
            projectId: $projectId,
            criteria: $criteria,
            stage: ScreeningStage::TITLE_ABSTRACT,
            mode: $this->mode(),
            model: $this->stringOption('model'),
            councilModels: array_values((array) $this->option('council-model')),
            limit: $this->integerOption('limit'),
            workIds: array_values((array) $this->option('work-id')),
            queryIds: array_values((array) $this->option('query-id')),
            name: $this->stringOption('name'),
            storePrompt: (bool) $this->option('store-prompts'),
            storeRawResponse: (bool) $this->option('store-raw-responses'),
        ));

        $this->info('Screening complete.');
        $this->line('Run: '.$result->runId);
        $this->line('Total: '.$result->total);
        $this->line('Include: '.$result->included);
        $this->line('Needs review: '.$result->needsReview);
        $this->line('Exclude: '.$result->excluded);
        $this->line('Failed: '.$result->failed);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function mode(): ScreeningRunMode
    {
        return match ($this->option('mode')) {
            'llm_council', 'council' => ScreeningRunMode::LLM_COUNCIL,
            default => ScreeningRunMode::LLM_SINGLE,
        };
    }

    private function requiredString(string $name): ?string
    {
        $value = $this->stringOption($name);
        if ($value === null) {
            $this->error("Provide --{$name}.");
        }

        return $value;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        return is_numeric($value) ? (int) $value : null;
    }
}
```

Run a single-model screening pass:

```powershell
php artisan review:screen-project `
  --project=tomato_review `
  --include="crop image segmentation" `
  --exclude="medical imaging" `
  --mode=llm_single `
  --limit=25 `
  --name="Single model title abstract screening"
```

Run a council pass:

```powershell
php artisan review:screen-project `
  --project=tomato_review `
  --include="crop image segmentation" `
  --exclude="medical imaging" `
  --mode=llm_council `
  --council-model=openai/gpt-4.1-mini `
  --council-model=google/gemini-2.5-flash `
  --council-model=mistralai/mistral-small-2603 `
  --limit=25 `
  --name="Council title abstract screening"
```

## Build Human Adjudication

Human adjudication uses `AdjudicateScreeningDecisionsHandler`.

Create an input file such as `storage/adjudication/tomato-review.yml`:

```yaml
project: tomato_review
actor: reviewer-1
stage: title_abstract
criteria_hash: paste-screening-criteria-hash-here
decisions:
  - work_id: doi:10.1234/example
    decision: include
    confidence: 1.0
    reason: "Directly studies crop image segmentation."
    evidence:
      - "Title and abstract describe tomato segmentation."
  - work_id: internal:another-id
    decision: exclude
    confidence: 1.0
    reason: "Medical imaging scope."
    exclusion_basis:
      - "Not agricultural vision."
```

Create the command:

```powershell
php artisan make:command ReviewAdjudicate
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsCommand;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler;
use Nexus\Screening\Application\UseCase\HumanAdjudicationInput;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningStage;
use Symfony\Component\Yaml\Yaml;

class ReviewAdjudicate extends Command
{
    protected $signature = 'review:adjudicate {file : YAML adjudication file} {--run= : Human run id}';

    public function handle(AdjudicateScreeningDecisionsHandler $adjudicate): int
    {
        $payload = Yaml::parseFile(base_path((string) $this->argument('file')));

        $result = $adjudicate->handle(new AdjudicateScreeningDecisionsCommand(
            projectId: (string) $payload['project'],
            actorId: (string) $payload['actor'],
            stage: ScreeningStage::from((string) ($payload['stage'] ?? 'title_abstract')),
            criteriaHash: (string) $payload['criteria_hash'],
            decisions: array_map($this->decisionInput(...), $payload['decisions'] ?? []),
            screeningRunId: $this->option('run') ?: null,
            runName: 'Human adjudication',
        ));

        $this->info('Adjudication complete.');
        $this->line('Run: '.$result->runId);
        $this->line('Total: '.$result->total);
        $this->line('Include: '.$result->included);
        $this->line('Needs review: '.$result->needsReview);
        $this->line('Exclude: '.$result->excluded);

        return self::SUCCESS;
    }

    private function decisionInput(array $row): HumanAdjudicationInput
    {
        return new HumanAdjudicationInput(
            workId: (string) $row['work_id'],
            decision: ScreeningDecision::from((string) $row['decision']),
            reason: (string) $row['reason'],
            evidence: array_values($row['evidence'] ?? []),
            uncertainty: array_values($row['uncertainty'] ?? []),
            exclusionBasis: array_values($row['exclusion_basis'] ?? []),
            confidence: (float) ($row['confidence'] ?? 1.0),
        );
    }
}
```

Run:

```powershell
php artisan review:adjudicate storage/adjudication/tomato-review.yml
```

## Compare Screening Runs

Create:

```powershell
php artisan make:command ReviewCompareScreening
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsCommand;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler;
use Nexus\Screening\Domain\ScreeningStage;

class ReviewCompareScreening extends Command
{
    protected $signature = 'review:compare-screening
        {--project= : Project ID}
        {--baseline= : Baseline run ID}
        {--candidate= : Candidate run ID}
        {--stage=title_abstract : Screening stage}
        {--no-rows : Omit row details}';

    public function handle(CompareScreeningRunsHandler $compare): int
    {
        $result = $compare->handle(new CompareScreeningRunsCommand(
            projectId: (string) $this->option('project'),
            baselineRunId: (string) $this->option('baseline'),
            candidateRunId: (string) $this->option('candidate'),
            stage: ScreeningStage::from((string) $this->option('stage')),
            includeRows: ! (bool) $this->option('no-rows'),
        ));

        $this->info('Comparison complete.');
        $this->line('Comparable: '.$result->comparableTotal);
        $this->line('Agreement: '.$result->agreementCount.' ('.round($result->agreementRate * 100, 1).'%)');
        $this->line('Disagreement: '.$result->disagreementCount.' ('.round($result->disagreementRate * 100, 1).'%)');

        $this->table(
            ['From', 'To', 'Count'],
            collect($result->transitionCounts)
                ->flatMap(fn (array $targets, string $from) => collect($targets)
                    ->map(fn (int $count, string $to) => [$from, $to, $count]))
                ->values()
                ->all(),
        );

        return self::SUCCESS;
    }
}
```

Run:

```powershell
php artisan review:compare-screening `
  --project=tomato_review `
  --baseline=single-model-run-id `
  --candidate=human-run-id
```

## Review Workflow

A robust screening workflow is:

1. Lock the corpus.
2. Run single-model screening.
3. Run council screening for uncertain or high-risk work.
4. Record human adjudication.
5. Compare automated runs against human decisions.
6. Export final included works.

Core keeps the important evidence:

- screening runs,
- decisions,
- model votes,
- prompt and raw response audit fields when enabled,
- human decisions,
- comparison outputs.

Your host owns:

- file format for human adjudication,
- command UX,
- reviewer identity model,
- reporting format.

## Implementation References

- `src/Screening/Application/UseCase/ScreenCorpusCommand.php`
- `src/Screening/Application/UseCase/AdjudicateScreeningDecisionsCommand.php`
- `src/Screening/Application/UseCase/HumanAdjudicationInput.php`
- `src/Screening/Application/UseCase/CompareScreeningRunsCommand.php`
- `src/Screening/Application/UseCase/ScreeningRunComparisonResult.php`
- `docs/v1.0/modules/05-core-screening-and-adjudication.md`