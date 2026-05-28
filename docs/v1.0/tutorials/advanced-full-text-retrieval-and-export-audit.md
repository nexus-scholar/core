# Advanced Full-Text Retrieval And Export Audit

This tutorial shows how to build a host command that retrieves legal open-access full text for a project corpus and then inspects the audit trail through core read ports.

You will wire:

- `review:retrieve-full-text`,
- `review:full-text-audit`,
- `review:export-audit`.

The goal is to keep retrieval and export workflows auditable. Full-text retrieval is inherently partial: some works have legal open-access artifacts, some do not, and some source attempts fail. Core records those outcomes instead of hiding them.

## Configure Retrieval Sources

In `.env`:

```dotenv
NEXUS_PDF_DISK=public
NEXUS_UNPAYWALL_EMAIL=you@example.com

NEXUS_FULL_TEXT_DIRECT_ENABLED=true
NEXUS_UNPAYWALL_ENABLED=true
NEXUS_PMC_ENABLED=true
NEXUS_EUROPE_PMC_ENABLED=true
NEXUS_FULL_TEXT_ARXIV_ENABLED=true
NEXUS_FULL_TEXT_OPENALEX_ENABLED=true
NEXUS_FULL_TEXT_S2_ENABLED=true
```

Core keeps shadow-library retrieval disabled.

Clear config:

```powershell
php artisan config:clear
```

## Build Retrieval For A Project

This command loads authoritative project membership through `ProjectCorpusWorksPort`, resolves works through `WorkRepositoryPort`, and sends each work to `RetrieveFullTextHandler`.

```powershell
php artisan make:command ReviewRetrieveFullText
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

class ReviewRetrieveFullText extends Command
{
    protected $signature = 'review:retrieve-full-text
        {--project= : Project ID}
        {--destination= : Storage folder}
        {--limit= : Max works}
        {--max-attempts=2 : Max attempts per source}
        {--max-bytes=50000000 : Max artifact size}
        {--cooldown=3600 : Failed-attempt cooldown seconds}';

    public function handle(
        ProjectCorpusWorksPort $projectWorks,
        WorkRepositoryPort $works,
        RetrieveFullTextHandler $retrieve,
    ): int {
        $projectId = $this->requiredString('project');
        if ($projectId === null) {
            return self::FAILURE;
        }

        $ids = array_map(
            fn (string $id) => new WorkId(WorkIdNamespace::INTERNAL, $id),
            $projectWorks->workIds($projectId),
        );

        if ($this->integerOption('limit') !== null) {
            $ids = array_slice($ids, 0, $this->integerOption('limit'));
        }

        $rows = [];

        foreach ($works->findManyByIds($ids) as $work) {
            $result = $retrieve->handle(new RetrieveFullText(
                work: $work,
                destinationFolder: $this->stringOption('destination') ?? 'full-text/'.$projectId,
                maxDownloadAttempts: $this->integerOption('max-attempts') ?? 2,
                maxBytes: $this->integerOption('max-bytes') ?? 50_000_000,
                failedAttemptCooldownSeconds: $this->integerOption('cooldown') ?? 3600,
                projectId: $projectId,
            ));

            $rows[] = [
                $work->primaryId()?->toString() ?? '',
                $result->status->value,
                $result->sourceAlias ?? '',
                $result->filePath ?? '',
                $result->errorMessage ?? '',
            ];
        }

        $this->table(['Work', 'Status', 'Source', 'File', 'Error'], $rows);

        return self::SUCCESS;
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

Run:

```powershell
php artisan review:retrieve-full-text --project=tomato_review --limit=25
```

## Read Full-Text Audit Records

Use `FullTextFetchReaderPort`; do not query `pdf_fetches` directly from host code.

```powershell
php artisan make:command ReviewFullTextAudit
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;

class ReviewFullTextAudit extends Command
{
    protected $signature = 'review:full-text-audit
        {--project= : Project ID}
        {--work= : Work ID}
        {--limit=25 : Max records}';

    public function handle(FullTextFetchReaderPort $fetches): int
    {
        $projectId = $this->stringOption('project');
        $workId = $this->stringOption('work');

        if (($projectId === null) === ($workId === null)) {
            $this->error('Provide exactly one of --project or --work.');

            return self::FAILURE;
        }

        $records = $workId !== null
            ? $fetches->forWork($workId, $this->limit())
            : $fetches->forProject($projectId, $this->limit());

        $this->table(
            ['Work', 'Source', 'Status', 'HTTP', 'File', 'Attempted'],
            array_map(fn ($record) => [
                $record->workId,
                $record->sourceAlias,
                $record->status->value,
                (string) ($record->httpStatus ?? ''),
                $record->filePath ?? '',
                $record->attemptedAt->format(DATE_ATOM),
            ], $records),
        );

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function limit(): int
    {
        return max(1, (int) $this->option('limit'));
    }
}
```

Run:

```powershell
php artisan review:full-text-audit --project=tomato_review
php artisan review:full-text-audit --work=doi:10.5555/example
```

## Read Export History

If your host also exposes export commands, pair them with export-history inspection.

```php
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;

$records = $exports->latest(
    projectId: 'tomato_review',
    type: 'bibliography',
    limit: 10,
);
```

Record this in your user-facing docs:

- which project was exported,
- whether the corpus was locked,
- which snapshot was used,
- whether the export was final and citable,
- who requested it,
- where the artifact was stored.

## Operational Notes

Full-text retrieval should usually run after locking because locked membership is stable. Retrieval itself can still produce skipped or failed results:

- no primary identifier,
- no legal source candidate,
- recent failed source cooldown,
- invalid PDF/XML,
- oversized artifact,
- source timeout,
- network failure.

That is expected. The audit record is the durable evidence.

## What Core Provides

Core provides:

- source candidate resolution,
- legal open-access source adapters,
- artifact validation,
- deterministic storage paths,
- PDF/XML/text artifact handling,
- cooldown for repeated source failures,
- fetch audit persistence,
- read ports for fetch audit and export history.

Your host provides:

- command UX,
- batch size,
- artifact destination naming,
- reporting format,
- any project-specific inclusion filters.

## Implementation References

- `src/Dissemination/Application/UseCase/RetrieveFullText.php`
- `src/Dissemination/Application/UseCase/RetrieveFullTextHandler.php`
- `src/Dissemination/Application/Dto/FullTextResult.php`
- `src/Dissemination/Domain/Port/FullTextFetchReaderPort.php`
- `src/Dissemination/Domain/Port/ExportHistoryReaderPort.php`
- `docs/v1.0/modules/07-core-full-text-and-dissemination.md`