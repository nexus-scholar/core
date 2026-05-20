<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Plan;

final readonly class SearchPlan
{
    /**
     * @param list<SearchPlanItem> $items
     */
    public function __construct(
        public string $projectId,
        public array $items,
        public string $sourceName = 'inline',
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<SearchPlanItem>
     */
    public function select(SearchPlanRunOptions $options): array
    {
        $items = $this->items;

        if ($options->onlyIds !== []) {
            $requested = array_fill_keys($options->onlyIds, true);
            $items = array_values(array_filter(
                $items,
                static fn (SearchPlanItem $item): bool => isset($requested[$item->id]),
            ));

            $found = array_fill_keys(array_map(
                static fn (SearchPlanItem $item): string => $item->id,
                $items,
            ), true);

            $missing = array_values(array_filter(
                $options->onlyIds,
                static fn (string $id): bool => ! isset($found[$id]),
            ));

            if ($missing !== []) {
                throw SearchPlanException::unknownIds($missing, $this->sourceName);
            }
        }

        if ($options->priority !== null) {
            $items = array_values(array_filter(
                $items,
                static fn (SearchPlanItem $item): bool => $item->priority === $options->priority,
            ));
        }

        return array_map(
            static fn (SearchPlanItem $item): SearchPlanItem => $item->withOverrides(
                projectId: $options->projectId,
                maxResults: $options->maxResults,
                providerAliases: $options->providerAliases,
            ),
            $items,
        );
    }
}
