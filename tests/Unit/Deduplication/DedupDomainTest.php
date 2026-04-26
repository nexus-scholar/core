<?php

declare(strict_types=1);

use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

// ── DedupClusterId ───────────────────────────────────────────────────────────

it('generates_unique_dedup_cluster_ids', function (): void {
    $id1 = DedupClusterId::generate();
    $id2 = DedupClusterId::generate();

    expect($id1->value)->not->toBe($id2->value);
    expect(strlen($id1->value))->toBeGreaterThan(0);
});

it('compares_cluster_id_equality', function (): void {
    $id1 = new DedupClusterId('abc');
    $id2 = new DedupClusterId('abc');
    $id3 = new DedupClusterId('xyz');

    expect($id1->equals($id2))->toBeTrue();
    expect($id1->equals($id3))->toBeFalse();
});

it('casts_cluster_id_to_string', function (): void {
    $id = new DedupClusterId('12345');
    expect((string) $id)->toBe('12345');
    expect($id->toString())->toBe('12345');
});

// ── Duplicate ────────────────────────────────────────────────────────────────

it('creates_duplicate_and_exposes_properties', function (): void {
    $workA = new WorkId(WorkIdNamespace::DOI, '10.x/a');
    $workB = new WorkId(WorkIdNamespace::DOI, '10.x/b');

    $duplicate = new Duplicate($workA, $workB, DuplicateReason::DOI_MATCH, 1.0);

    expect($duplicate->primaryId)->toBe($workA);
    expect($duplicate->secondaryId)->toBe($workB);
    expect($duplicate->reason)->toBe(DuplicateReason::DOI_MATCH);
    expect($duplicate->confidence)->toBe(1.0);
});

it('checks_if_duplicate_involves_specific_works', function (): void {
    $workA = new WorkId(WorkIdNamespace::DOI, '10.x/a');
    $workB = new WorkId(WorkIdNamespace::DOI, '10.x/b');
    $workC = new WorkId(WorkIdNamespace::DOI, '10.x/c');

    $duplicate = new Duplicate($workA, $workB, DuplicateReason::DOI_MATCH, 1.0);

    expect($duplicate->involves($workA))->toBeTrue();
    expect($duplicate->involves($workB))->toBeTrue();
    expect($duplicate->involves($workC))->toBeFalse();
});

it('checks_high_confidence', function (): void {
    $workA = new WorkId(WorkIdNamespace::DOI, '10.x/a');
    $workB = new WorkId(WorkIdNamespace::DOI, '10.x/b');

    $high = new Duplicate($workA, $workB, DuplicateReason::DOI_MATCH, 0.95);
    $low  = new Duplicate($workA, $workB, DuplicateReason::TITLE_FUZZY, 0.85);

    expect($high->isHighConfidence())->toBeTrue();
    expect($low->isHighConfidence())->toBeFalse();
});

it('converts_to_array', function (): void {
    $workA = new WorkId(WorkIdNamespace::DOI, '10.x/a');
    $workB = new WorkId(WorkIdNamespace::DOI, '10.x/b');

    $duplicate = new Duplicate($workA, $workB, DuplicateReason::DOI_MATCH, 1.0);
    $array = $duplicate->toArray();

    expect($array['primaryId'])->toBe('doi:10.x/a');
    expect($array['secondaryId'])->toBe('doi:10.x/b');
    expect($array['reason'])->toBe('doi_match');
    expect($array['confidence'])->toBe(1.0);
});
