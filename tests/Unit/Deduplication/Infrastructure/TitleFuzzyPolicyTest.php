<?php

declare(strict_types=1);

namespace Tests\Unit\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Infrastructure\TitleFuzzyPolicy;
use Nexus\Deduplication\Infrastructure\TitleNormalizer;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

$normalizer = new TitleNormalizer();

it('returns_empty_when_less_than_two_works', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer);

    $work = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Title',
        sourceProvider: 'test'
    );

    expect($policy->detect([$work]))->toBeEmpty();
    expect($policy->detect([]))->toBeEmpty();
});

it('detects_duplicate_with_small_typo', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer);

    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Deep Learning for Natural Language Processing',
        sourceProvider: 'test',
        year: 2020
    );

    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/b')),
        title: 'Deep Learning for Natural Language Procesing', // 1 char typo
        sourceProvider: 'test',
        year: 2020
    );

    $duplicates = $policy->detect([$workA, $workB]);

    expect($duplicates)->toHaveCount(1);
    expect($duplicates[0]->reason)->toBe(DuplicateReason::TITLE_FUZZY);
    expect($duplicates[0]->confidence)->toBeGreaterThan(0.9);
});

it('ignores_works_with_year_gap_greater_than_max', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer, maxYearGap: 1);

    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Same Title',
        sourceProvider: 'test',
        year: 2018
    );

    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/b')),
        title: 'Same Title',
        sourceProvider: 'test',
        year: 2020 // 2 years diff
    );

    $duplicates = $policy->detect([$workA, $workB]);
    expect($duplicates)->toBeEmpty();
});

it('does_not_ignore_works_if_year_is_null', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer, maxYearGap: 1);

    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Same Title',
        sourceProvider: 'test',
        year: null
    );

    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/b')),
        title: 'Same Title',
        sourceProvider: 'test',
        year: 2020
    );

    $duplicates = $policy->detect([$workA, $workB]);
    expect($duplicates)->toHaveCount(1);
});

it('ignores_completely_different_titles', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer);

    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Machine Learning',
        sourceProvider: 'test'
    );

    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/b')),
        title: 'Computer Vision',
        sourceProvider: 'test'
    );

    $duplicates = $policy->detect([$workA, $workB]);
    expect($duplicates)->toBeEmpty();
});

it('stress_tests_with_large_number_of_works', function () use ($normalizer): void {
    $policy = new TitleFuzzyPolicy($normalizer);
    $works = [];

    // Create 1000 works with random titles to test O(n log n) sorting + O(n) adjacent comparison
    for ($i = 0; $i < 1000; $i++) {
        $works[] = ScholarlyWork::reconstitute(
            ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, "10.1000/$i")),
            title: "A very standard academic paper title number " . random_int(1, 100000),
            sourceProvider: 'test'
        );
    }
    
    // Add two works that should be detected as duplicates
    $works[] = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, "10.1000/dup1")),
        title: "Specific Focus on Artificial Intelligence in Healthcare",
        sourceProvider: 'test'
    );
    $works[] = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, "10.1000/dup2")),
        title: "Specific Focus on Artificial Inteliggence in Healthcare", // typo
        sourceProvider: 'test'
    );

    $start = hrtime(true);
    $duplicates = $policy->detect($works);
    $end = hrtime(true);
    
    $elapsedMs = ($end - $start) / 1000000;
    
    // Should detect at least our intentional duplicate
    expect(count($duplicates))->toBeGreaterThanOrEqual(1);
    
    // Performance expectation: 1000 items should be processed well under 500ms
    // because it avoids n^2 levenshtein distance calculations.
    expect($elapsedMs)->toBeLessThan(500); 
});
