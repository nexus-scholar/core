<?php

declare(strict_types=1);

use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

// ── WorkIdNamespace ───────────────────────────────────────────────────────────

it('covers_all_supported_namespaces', function (): void {
    expect(WorkIdNamespace::cases())->toHaveCount(8);
});

it('is_backed_by_lowercase_string_values', function (): void {
    foreach (WorkIdNamespace::cases() as $case) {
        expect($case->value)->toMatch('/^[a-z0-9_]+$/');
    }
});

// ── WorkId ────────────────────────────────────────────────────────────────────

it('strips_https_doi_org_prefix_from_doi', function (): void {
    $id = new WorkId(WorkIdNamespace::DOI, 'https://doi.org/10.1234/abc');
    expect($id->value)->toBe('10.1234/abc');
});

it('strips_doi_colon_prefix_from_doi', function (): void {
    $id = new WorkId(WorkIdNamespace::DOI, 'doi:10.1234/abc');
    expect($id->value)->toBe('10.1234/abc');
});

it('does_not_strip_doi_characters_that_appear_in_the_prefix', function (): void {
    $id = new WorkId(WorkIdNamespace::DOI, 'https://doi.org/10.1037/h0043158');
    expect($id->value)->toBe('10.1037/h0043158'); // 'h' must NOT be stripped
});

it('lowercases_doi_value', function (): void {
    $id = new WorkId(WorkIdNamespace::DOI, '10.1234/ABC');
    expect($id->value)->toBe('10.1234/abc');
});

it('strips_arxiv_prefix', function (): void {
    $id = new WorkId(WorkIdNamespace::ARXIV, 'arxiv:2301.12345');
    expect($id->value)->toBe('2301.12345');
});

it('lowercases_all_namespace_values', function (): void {
    $id = new WorkId(WorkIdNamespace::OPENALEX, 'W1234567890');
    expect($id->value)->toBe('w1234567890');
});

it('compares_equal_when_namespace_and_value_match', function (): void {
    $a = new WorkId(WorkIdNamespace::DOI, '10.1234/abc');
    $b = new WorkId(WorkIdNamespace::DOI, '10.1234/abc');
    expect($a->equals($b))->toBeTrue();
});

it('does_not_equal_same_value_different_namespace', function (): void {
    $a = new WorkId(WorkIdNamespace::DOI, '10.1234/abc');
    $b = new WorkId(WorkIdNamespace::ARXIV, '10.1234/abc');
    expect($a->equals($b))->toBeFalse();
});

it('round_trips_through_toString_and_fromString', function (): void {
    $original = new WorkId(WorkIdNamespace::DOI, '10.1234/abc');
    $restored = WorkId::fromString($original->toString());
    expect($original->equals($restored))->toBeTrue();
});

it('accepts_doi_without_prefix', function (): void {
    $id = new WorkId(WorkIdNamespace::DOI, '10.1234/abc');
    expect($id->value)->toBe('10.1234/abc');
});

it('normalizes_doi_with_mixed_case', function (): void {
    $a = new WorkId(WorkIdNamespace::DOI, 'doi:10.1234/X');
    $b = new WorkId(WorkIdNamespace::DOI, 'https://doi.org/10.1234/X');
    expect($a->equals($b))->toBeTrue();
});

it('fromString_produces_same_id_as_constructor_for_doi', function (): void {
    $viaConstructor = new WorkId(WorkIdNamespace::DOI, 'https://doi.org/10.1234/X');
    $viaFromString  = WorkId::fromString('doi:10.1234/X');
    expect($viaConstructor->equals($viaFromString))->toBeTrue();
});

it('throws_on_fromString_missing_colon', function (): void {
    expect(fn () => WorkId::fromString('doi10.1234/abc'))
        ->toThrow(\InvalidArgumentException::class, 'Expected "<namespace>:<value>"');
});

it('throws_on_fromString_empty_value', function (): void {
    expect(fn () => WorkId::fromString('doi:'))
        ->toThrow(\InvalidArgumentException::class, 'Expected "<namespace>:<value>"');
});

it('throws_on_fromString_invalid_namespace', function (): void {
    expect(fn () => WorkId::fromString('invalid:1234'))
        ->toThrow(\InvalidArgumentException::class, 'Unknown WorkId namespace');
});

// ── WorkIdSet ─────────────────────────────────────────────────────────────────

it('returns_doi_as_primary_when_present', function (): void {
    $set = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::ARXIV, '2301.12345'),
        new WorkId(WorkIdNamespace::DOI, '10.1234/abc'),
    ]);

    expect($set->primary()?->namespace)->toBe(WorkIdNamespace::DOI);
});

it('falls_back_to_openalex_when_doi_absent', function (): void {
    $set = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::ARXIV, '2301.12345'),
        new WorkId(WorkIdNamespace::OPENALEX, 'w1234567890'),
    ]);

    expect($set->primary()?->namespace)->toBe(WorkIdNamespace::OPENALEX);
});

it('returns_null_primary_when_empty', function (): void {
    expect(WorkIdSet::empty()->primary())->toBeNull();
});

it('detects_overlap_via_shared_doi', function (): void {
    $a = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/abc')]);
    $b = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::DOI, '10.1234/abc'),
        new WorkId(WorkIdNamespace::ARXIV, '2301.99999'),
    ]);

    expect($a->hasOverlapWith($b))->toBeTrue();
});

it('detects_overlap_via_shared_arxiv_id', function (): void {
    $a = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.12345')]);
    $b = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.12345')]);
    expect($a->hasOverlapWith($b))->toBeTrue();
});

it('returns_false_overlap_when_no_shared_ids', function (): void {
    $a = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/aaa')]);
    $b = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/bbb')]);
    expect($a->hasOverlapWith($b))->toBeFalse();
});

it('remains_immutable_after_add', function (): void {
    $original = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/aaa')]);
    $newSet   = $original->add(new WorkId(WorkIdNamespace::ARXIV, '2301.12345'));

    expect($original->count())->toBe(1);
    expect($newSet->count())->toBe(2);
});

it('merges_two_sets_and_removes_duplicates', function (): void {
    $a = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/aaa')]);
    $b = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::DOI, '10.1234/aaa'),
        new WorkId(WorkIdNamespace::ARXIV, '2301.12345'),
    ]);

    $merged = $a->merge($b);
    expect($merged->count())->toBe(2);
});

it('counts_correctly', function (): void {
    $set = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::DOI, '10.1234/a'),
        new WorkId(WorkIdNamespace::ARXIV, '2301.1'),
        new WorkId(WorkIdNamespace::S2, 'abc123'),
    ]);

    expect($set->count())->toBe(3);
});
