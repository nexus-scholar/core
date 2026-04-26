<?php

declare(strict_types=1);

namespace Tests\Unit\Deduplication\Infrastructure;

use Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('clusters two works with identical S2 IDs but different DOIs', function () {
    $policy = new NamespaceMatchPolicy(WorkIdNamespace::S2);

    $work1 = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(
            new WorkId(WorkIdNamespace::S2, '12345'),
            new WorkId(WorkIdNamespace::DOI, '10.1000/xyz123')
        ),
        title: 'Title A',
        sourceProvider: 'test'
    );

    $work2 = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(
            new WorkId(WorkIdNamespace::S2, '12345'),
            new WorkId(WorkIdNamespace::DOI, '10.1000/different')
        ),
        title: 'Title A Variation',
        sourceProvider: 'test'
    );

    $duplicates = $policy->detect([$work1, $work2]);

    expect($duplicates)->toHaveCount(1);
    expect($duplicates[0]->primaryId)->toEqual($work1->primaryId());
    expect($duplicates[0]->secondaryId)->toEqual($work2->primaryId());
    expect($duplicates[0]->reason->value)->toBe('s2_match');
});
