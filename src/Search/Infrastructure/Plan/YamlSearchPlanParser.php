<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Plan;

use Nexus\Search\Application\Plan\SearchPlan;
use Nexus\Search\Application\Plan\SearchPlanException;
use Nexus\Search\Application\Plan\SearchPlanItem;
use Nexus\Search\Application\Plan\SearchPlanParserPort;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class YamlSearchPlanParser implements SearchPlanParserPort
{
    public function parseFile(string $path): SearchPlan
    {
        if (! is_file($path)) {
            throw SearchPlanException::invalid("Search plan file not found: {$path}");
        }

        return $this->parseString((string) file_get_contents($path), basename($path));
    }

    public function parseString(string $contents, string $sourceName = 'inline'): SearchPlan
    {
        try {
            $data = Yaml::parse($contents);
        } catch (ParseException $error) {
            throw SearchPlanException::invalid("Search plan {$sourceName} is not valid YAML: {$error->getMessage()}");
        }

        if (! is_array($data)) {
            throw SearchPlanException::invalid("Search plan {$sourceName} must be a YAML mapping.");
        }

        return $this->parseArray($data, $sourceName);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function parseArray(array $data, string $sourceName = 'inline'): SearchPlan
    {
        $projectId = $this->stringValue($data['project'] ?? $data['project_id'] ?? null) ?? 'default-project';
        $rawItems = $data['searches'] ?? $data['queries'] ?? [];

        if (! is_array($rawItems)) {
            throw SearchPlanException::invalid("Search plan {$sourceName} must contain a 'searches' or 'queries' list.");
        }

        $rootProviders = $this->providerAliases($data['providers'] ?? []);
        $items = [];

        foreach (array_values($rawItems) as $index => $rawItem) {
            if (! is_array($rawItem)) {
                throw SearchPlanException::invalid("Search plan {$sourceName} entry {$index} must be a mapping.");
            }

            $items[] = $this->parseItem($rawItem, $index, $projectId, $rootProviders, $sourceName);
        }

        return new SearchPlan(
            projectId: $projectId,
            items: $items,
            sourceName: $sourceName,
        );
    }

    /**
     * @param array<string, mixed> $rawItem
     * @param list<string> $rootProviders
     */
    private function parseItem(
        array $rawItem,
        int $index,
        string $rootProjectId,
        array $rootProviders,
        string $sourceName,
    ): SearchPlanItem {
        $id = $this->requiredString($rawItem['id'] ?? null, "Search plan {$sourceName} entry {$index} is missing an id.");
        $query = $this->requiredString(
            $rawItem['query'] ?? $rawItem['text'] ?? null,
            "Search plan {$sourceName} query {$id} is missing query text.",
        );

        $metadata = $rawItem['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw SearchPlanException::invalid("Search plan {$sourceName} query {$id} metadata must be a mapping.");
        }

        $priority = $this->stringValue($rawItem['priority'] ?? $metadata['priority'] ?? null);
        $projectId = $this->stringValue($rawItem['project'] ?? $rawItem['project_id'] ?? null) ?? $rootProjectId;
        $providers = array_key_exists('providers', $rawItem)
            ? $this->providerAliases($rawItem['providers'])
            : $rootProviders;

        return new SearchPlanItem(
            id: $id,
            label: $this->stringValue($rawItem['label'] ?? null) ?? $id,
            query: $query,
            projectId: $projectId,
            maxResults: $this->positiveInt($rawItem['limit'] ?? $rawItem['max_results'] ?? 50, $id, $sourceName),
            yearFrom: $this->nullableInt($rawItem['year_from'] ?? $rawItem['year_min'] ?? null),
            yearTo: $this->nullableInt($rawItem['year_to'] ?? $rawItem['year_max'] ?? null),
            providerAliases: $providers,
            metadata: $metadata,
            priority: $priority,
            includeTitleAbstract: $this->nullableString($rawItem['include_title_abstract'] ?? null),
            excludeTitleAbstract: $this->nullableString($rawItem['exclude_title_abstract'] ?? null),
            sourceIndex: $index,
        );
    }

    private function requiredString(mixed $value, string $message): string
    {
        $string = $this->stringValue($value);

        if ($string === null) {
            throw SearchPlanException::invalid($message);
        }

        return $string;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function positiveInt(mixed $value, string $id, string $sourceName): int
    {
        $integer = (int) $value;

        if ($integer < 1) {
            throw SearchPlanException::invalid("Search plan {$sourceName} query {$id} limit must be at least 1.");
        }

        return $integer;
    }

    /**
     * @return list<string>
     */
    private function providerAliases(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $aliases = is_array($value) ? $value : explode(',', (string) $value);
        $normalized = [];

        foreach ($aliases as $alias) {
            if (! is_scalar($alias)) {
                continue;
            }

            $alias = strtolower(trim((string) $alias));

            if ($alias !== '') {
                $normalized[$alias] = $alias;
            }
        }

        return array_values($normalized);
    }
}
