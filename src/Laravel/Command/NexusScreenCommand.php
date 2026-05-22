<?php

declare(strict_types=1);

namespace Nexus\Laravel\Command;

use Illuminate\Console\Command;
use Nexus\Screening\Application\UseCase\ScreenCorpusCommand;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class NexusScreenCommand extends Command
{
    protected $signature = 'nexus:screen
                            {--project= : Project ID whose persisted works should be screened}
                            {--criteria= : JSON or YAML criteria file}
                            {--include=* : Inclusion criterion; repeatable}
                            {--exclude=* : Exclusion criterion; repeatable}
                            {--mode=llm : llm or council}
                            {--stage=title_abstract : Screening stage}
                            {--model= : Single-model screening model}
                            {--council-models= : Comma-separated council model list}
                            {--max= : Maximum works to screen}
                            {--work-ids= : Comma-separated internal work UUIDs}
                            {--query-ids= : Comma-separated search query UUIDs}
                            {--name= : Human-readable screening run name}
                            {--store-prompts : Persist rendered prompts in screening_votes}
                            {--store-raw-responses : Persist raw LLM responses in screening_votes}';

    protected $description = 'Screen persisted project works with the reusable Nexus screening engine';

    public function handle(ScreenCorpusHandler $handler): int
    {
        try {
            $projectId = $this->stringOption('project');
            if ($projectId === null) {
                throw new \InvalidArgumentException('The --project option is required.');
            }

            $result = $handler->handle(new ScreenCorpusCommand(
                projectId: $projectId,
                criteria: $this->criteria(),
                stage: ScreeningStage::from($this->stringOption('stage') ?? ScreeningStage::TITLE_ABSTRACT->value),
                mode: $this->mode(),
                model: $this->model(),
                councilModels: $this->councilModels(),
                limit: $this->limit(),
                workIds: $this->csvOption('work-ids'),
                queryIds: $this->csvOption('query-ids'),
                name: $this->stringOption('name'),
                context: ['project' => $projectId],
                temperature: (float) config('nexus.screening.llm.temperature', 0),
                maxTokens: (int) config('nexus.screening.llm.max_tokens', 600),
                storePrompt: (bool) $this->option('store-prompts'),
                storeRawResponse: (bool) $this->option('store-raw-responses'),
            ));
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Screening complete.');
        $this->line(sprintf(
            'Run: %s | Total: %d | Include: %d | Needs review: %d | Exclude: %d | Failed: %d',
            $result->runId,
            $result->total,
            $result->included,
            $result->needsReview,
            $result->excluded,
            $result->failed,
        ));

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function criteria(): ScreeningCriteria
    {
        $file = $this->stringOption('criteria');
        if ($file !== null) {
            $criteria = $this->criteriaFromFile($file);
        } else {
            $criteria = [
                'include' => $this->arrayOption('include'),
                'exclude' => $this->arrayOption('exclude'),
            ];
        }

        if (($criteria['include'] ?? []) === [] && ($criteria['exclude'] ?? []) === []) {
            throw new \InvalidArgumentException('Provide --criteria, --include, or --exclude screening criteria.');
        }

        return ScreeningCriteria::fromArray($criteria);
    }

    /**
     * @return array<string, mixed>
     */
    private function criteriaFromFile(string $path): array
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException("Criteria file not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $parsed = match ($extension) {
            'json' => json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR),
            default => Yaml::parseFile($path),
        };

        if (! is_array($parsed)) {
            throw new \InvalidArgumentException('Criteria file must parse to an object.');
        }

        return $parsed;
    }

    private function mode(): ScreeningRunMode
    {
        return match (strtolower($this->stringOption('mode') ?? 'llm')) {
            'llm', 'single', 'llm_single' => ScreeningRunMode::LLM_SINGLE,
            'council', 'llm_council' => ScreeningRunMode::LLM_COUNCIL,
            default => throw new \InvalidArgumentException('Unsupported screening mode. Use llm or council.'),
        };
    }

    private function model(): ?string
    {
        return $this->stringOption('model') ?? (string) config('nexus.screening.llm.model', 'openai/gpt-4.1-mini');
    }

    /**
     * @return list<string>
     */
    private function councilModels(): array
    {
        $option = $this->csvOption('council-models');
        if ($option !== []) {
            return $option;
        }

        $models = config('nexus.screening.llm.council.models', []);

        return is_array($models)
            ? array_values(array_filter(array_map(static fn (mixed $model): string => trim((string) $model), $models)))
            : [];
    }

    private function limit(): ?int
    {
        $value = $this->stringOption('max');

        return $value === null ? null : (int) $value;
    }

    /**
     * @return list<string>
     */
    private function csvOption(string $name): array
    {
        $value = $this->stringOption($name);
        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value),
        )));
    }

    /**
     * @return list<string>
     */
    private function arrayOption(string $name): array
    {
        $value = $this->option($name);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        )));
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
