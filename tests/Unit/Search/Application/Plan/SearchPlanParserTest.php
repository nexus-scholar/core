<?php

declare(strict_types=1);

use Nexus\Search\Application\Plan\SearchPlanException;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Infrastructure\Plan\YamlSearchPlanParser;

it('parses nexus-cli v4 search files into reusable search plan items', function (): void {
    $parser = new YamlSearchPlanParser();
    $plan = $parser->parseFile(__DIR__ . '/../../../../Fixture/search_plans/nexus_cli_v4_searches.yml');

    expect($plan->projectId)->toBe('image_texture_slr')
        ->and($plan->sourceName)->toBe('nexus_cli_v4_searches.yml')
        ->and($plan->items)->toHaveCount(2);

    $first = $plan->items[0];
    expect($first->id)->toBe('TX_CORE01')
        ->and($first->label)->toBe('TX_CORE01')
        ->and($first->projectId)->toBe('image_texture_slr')
        ->and($first->maxResults)->toBe(100)
        ->and($first->yearFrom)->toBe(2023)
        ->and($first->providerAliases)->toBe(['openalex', 'arxiv'])
        ->and($first->priority)->toBe('high')
        ->and($first->metadata['theme'])->toBe('core_texture_analysis')
        ->and($first->includeTitleAbstract)->toBe('Include texture-analysis methods in image processing.')
        ->and($first->excludeTitleAbstract)->toBe('Exclude descriptive-only uses of texture.');

    $second = $plan->items[1];
    expect($second->providerAliases)->toBe(['semantic_scholar', 'openalex'])
        ->and($second->priority)->toBe('medium');
});

it('parses legacy queries syntax without behavior loss', function (): void {
    $parser = new YamlSearchPlanParser();
    $plan = $parser->parseFile(__DIR__ . '/../../../../Fixture/search_plans/legacy_queries.yml');

    $item = $plan->items[0];

    expect($plan->projectId)->toBe('legacy_project')
        ->and($item->query)->toBe('machine learning')
        ->and($item->maxResults)->toBe(25)
        ->and($item->yearFrom)->toBe(2020)
        ->and($item->yearTo)->toBe(2024)
        ->and($item->priority)->toBe('low');
});

it('parses include_raw_data at root and item level', function (): void {
    $parser = new YamlSearchPlanParser();
    $plan = $parser->parseString(<<<'YAML'
project: graph-project
include_raw_data: true
searches:
  - id: raw
    query: texture analysis
  - id: plain
    query: segmentation
    include_raw_data: false
YAML);

    expect($plan->items[0]->includeRawData)->toBeTrue()
        ->and($plan->items[0]->toSearchCommand()->query->includeRawData)->toBeTrue()
        ->and($plan->items[0]->metadataForRun()['include_raw_data'])->toBeTrue()
        ->and($plan->items[1]->includeRawData)->toBeFalse()
        ->and($plan->items[1]->toSearchCommand()->query->includeRawData)->toBeFalse()
        ->and($plan->items[1]->metadataForRun())->not->toHaveKey('include_raw_data');
});

it('filters selected ids and priority with useful errors', function (): void {
    $parser = new YamlSearchPlanParser();
    $plan = $parser->parseFile(__DIR__ . '/../../../../Fixture/search_plans/nexus_cli_v4_searches.yml');

    $selected = $plan->select(new SearchPlanRunOptions(
        onlyIds: ['TX_CORE01'],
        priority: 'high',
        providerAliases: ['crossref'],
    ));

    expect($selected)->toHaveCount(1)
        ->and($selected[0]->id)->toBe('TX_CORE01')
        ->and($selected[0]->providerAliases)->toBe(['crossref']);

    expect(fn () => $plan->select(new SearchPlanRunOptions(onlyIds: ['missing'])))
        ->toThrow(SearchPlanException::class, 'Search plan nexus_cli_v4_searches.yml does not contain query ID: missing.');
});

it('rejects invalid yaml shapes with clear messages', function (): void {
    $parser = new YamlSearchPlanParser();

    expect(fn () => $parser->parseString('searches: nope', 'bad.yml'))
        ->toThrow(SearchPlanException::class, "Search plan bad.yml must contain a 'searches' or 'queries' list.");

    expect(fn () => $parser->parseString(<<<'YAML'
searches:
  - id: Q1
    query: test
    metadata: bad
YAML, 'bad-metadata.yml'))
        ->toThrow(SearchPlanException::class, 'Search plan bad-metadata.yml query Q1 metadata must be a mapping.');
});
