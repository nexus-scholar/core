<?php

declare(strict_types=1);

use Nexus\Search\Domain\Exception\InvalidSearchTerm;
use Nexus\Search\Domain\Exception\InvalidYearRange;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;

// ── SearchTerm ────────────────────────────────────────────────────────────────

it('rejects_empty_string', function (): void {
    expect(fn () => new SearchTerm(''))->toThrow(InvalidSearchTerm::class);
});

it('rejects_single_character_string', function (): void {
    expect(fn () => new SearchTerm('a'))->toThrow(InvalidSearchTerm::class);
});

it('rejects_whitespace_only_string', function (): void {
    expect(fn () => new SearchTerm('   '))->toThrow(InvalidSearchTerm::class);
});

it('accepts_two_character_string', function (): void {
    expect((new SearchTerm('ab'))->value)->toBe('ab');
});

it('accepts_multilingual_term', function (): void {
    $term = new SearchTerm('深度学习');
    expect($term->value)->toBe('深度学习');
});

it('trims_whitespace_before_validation', function (): void {
    expect(fn () => new SearchTerm(' a '))->toThrow(InvalidSearchTerm::class);
});

// ── YearRange ─────────────────────────────────────────────────────────────────

it('rejects_inverted_range', function (): void {
    expect(fn () => YearRange::between(2020, 2010))->toThrow(InvalidYearRange::class);
});

it('rejects_year_below_1000', function (): void {
    expect(fn () => YearRange::since(999))->toThrow(InvalidYearRange::class);
});

it('accepts_null_from_or_to', function (): void {
    $r = new YearRange(from: 2020);
    expect($r->from)->toBe(2020);
    expect($r->to)->toBeNull();
});

it('contains_year_within_range', function (): void {
    $r = YearRange::between(2010, 2020);
    expect($r->contains(2015))->toBeTrue();
});

it('excludes_year_outside_range', function (): void {
    $r = YearRange::between(2010, 2020);
    expect($r->contains(2025))->toBeFalse();
});

it('contains_all_years_when_unbounded', function (): void {
    $r = YearRange::unbounded();
    expect($r->contains(1500))->toBeTrue();
    expect($r->contains(2099))->toBeTrue();
});

it('detects_overlapping_ranges', function (): void {
    $a = YearRange::between(2010, 2015);
    $b = YearRange::between(2014, 2020);
    expect($a->overlaps($b))->toBeTrue();
});

// ── SearchQuery ───────────────────────────────────────────────────────────────

it('generates_crypto_random_id', function (): void {
    $q1 = new SearchQuery(new SearchTerm('machine learning'));
    $q2 = new SearchQuery(new SearchTerm('machine learning'));
    expect($q1->id)->toStartWith('Q');
    expect($q1->id)->not->toBe($q2->id);
});

it('does_not_use_uniqid', function (): void {
    // uniqid() returns 13-char hex; our ID is 'Q' + 10 hex chars = 11 chars
    $q = new SearchQuery(new SearchTerm('test'));
    expect(strlen($q->id))->toBe(11);
});

it('produces_same_cache_key_for_identical_queries', function (): void {
    $q1 = new SearchQuery(new SearchTerm('AI'), id: 'Q_same');
    $q2 = new SearchQuery(new SearchTerm('AI'), id: 'Q_same');
    expect($q1->cacheKey(['openalex']))->toBe($q2->cacheKey(['openalex']));
});

it('produces_different_cache_key_when_language_differs', function (): void {
    $base = ['term' => new SearchTerm('AI')];
    $q1   = new SearchQuery($base['term'], language: new \Nexus\Shared\ValueObject\LanguageCode('en'));
    $q2   = new SearchQuery($base['term'], language: new \Nexus\Shared\ValueObject\LanguageCode('fr'));
    expect($q1->cacheKey())->not->toBe($q2->cacheKey());
});

it('produces_different_cache_key_when_max_results_differs', function (): void {
    $q1 = new SearchQuery(new SearchTerm('AI'), maxResults: 50);
    $q2 = new SearchQuery(new SearchTerm('AI'), maxResults: 100);
    expect($q1->cacheKey())->not->toBe($q2->cacheKey());
});

it('produces_different_cache_key_when_offset_differs', function (): void {
    $q1 = new SearchQuery(new SearchTerm('AI'), offset: 0);
    $q2 = new SearchQuery(new SearchTerm('AI'), offset: 100);
    expect($q1->cacheKey())->not->toBe($q2->cacheKey());
});

it('produces_different_cache_key_when_providers_differ', function (): void {
    $q = new SearchQuery(new SearchTerm('AI'));
    expect($q->cacheKey(['openalex']))->not->toBe($q->cacheKey(['crossref']));
});

it('is_provider_order_insensitive_in_cache_key', function (): void {
    $q = new SearchQuery(new SearchTerm('AI'));
    expect($q->cacheKey(['crossref', 'openalex']))->toBe($q->cacheKey(['openalex', 'crossref']));
});

it('advances_offset_on_next_page', function (): void {
    $q    = new SearchQuery(new SearchTerm('AI'), maxResults: 25, offset: 0);
    $next = $q->nextPage();
    expect($next->offset)->toBe(25);
});

it('identifies_first_page', function (): void {
    $q = new SearchQuery(new SearchTerm('AI'), offset: 0);
    expect($q->isFirstPage())->toBeTrue();

    $q2 = $q->nextPage();
    expect($q2->isFirstPage())->toBeFalse();
});
