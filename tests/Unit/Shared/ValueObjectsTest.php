<?php

declare(strict_types=1);

use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\LanguageCode;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;

// ── OrcidId ──────────────────────────────────────────────────────────────────

it('accepts_valid_orcid', function (): void {
    $orcid = new OrcidId('0000-0002-1825-0097');
    expect($orcid->value)->toBe('0000-0002-1825-0097');
    expect($orcid->toString())->toBe('0000-0002-1825-0097');
});

it('accepts_valid_orcid_with_X_checksum', function (): void {
    $orcid = new OrcidId('0000-0001-5109-370X');
    expect($orcid->value)->toBe('0000-0001-5109-370X');
});

it('throws_on_invalid_orcid_format', function (): void {
    expect(fn () => new OrcidId('invalid-orcid'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid ORCID format');

    expect(fn () => new OrcidId('0000-0002-1825-009Y'))
        ->toThrow(\InvalidArgumentException::class);
});

it('compares_orcid_equality', function (): void {
    $a = new OrcidId('0000-0002-1825-0097');
    $b = new OrcidId('0000-0002-1825-0097');
    $c = new OrcidId('0000-0001-5109-370X');

    expect($a->equals($b))->toBeTrue();
    expect($a->equals($c))->toBeFalse();
});

// ── LanguageCode ─────────────────────────────────────────────────────────────

it('accepts_valid_language_codes', function (): void {
    expect((new LanguageCode('en'))->value)->toBe('en');
    expect((new LanguageCode('fr-CA'))->value)->toBe('fr-CA');
});

it('throws_on_invalid_language_codes', function (): void {
    expect(fn () => new LanguageCode('english'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid language code');
    
    expect(fn () => new LanguageCode('e'))
        ->toThrow(\InvalidArgumentException::class);
});

it('provides_static_helpers_for_common_languages', function (): void {
    expect(LanguageCode::english()->value)->toBe('en');
    expect(LanguageCode::french()->value)->toBe('fr');
    expect(LanguageCode::arabic()->value)->toBe('ar');
});

it('compares_language_code_equality', function (): void {
    $en1 = LanguageCode::english();
    $en2 = new LanguageCode('en');
    $fr = LanguageCode::french();

    expect($en1->equals($en2))->toBeTrue();
    expect($en1->equals($fr))->toBeFalse();
});

// ── Venue ────────────────────────────────────────────────────────────────────

it('stores_venue_properties', function (): void {
    $venue = new Venue('Nature', '0028-0836', 'journal', 'Nature Publishing Group');

    expect($venue->name)->toBe('Nature');
    expect($venue->issn)->toBe('0028-0836');
    expect($venue->type)->toBe('journal');
    expect($venue->publisher)->toBe('Nature Publishing Group');
});

it('identifies_journal_and_conference_types', function (): void {
    $journal = new Venue('Nature', null, 'journal');
    $conf = new Venue('NeurIPS', null, 'conference');
    $repo = new Venue('arXiv', null, 'repository');

    expect($journal->isJournal())->toBeTrue();
    expect($journal->isConference())->toBeFalse();

    expect($conf->isConference())->toBeTrue();
    expect($conf->isJournal())->toBeFalse();

    expect($repo->isJournal())->toBeFalse();
    expect($repo->isConference())->toBeFalse();
});

// ── Author ───────────────────────────────────────────────────────────────────

it('computes_full_name', function (): void {
    $authorWithGiven = new Author('Smith', 'John');
    expect($authorWithGiven->fullName())->toBe('John Smith');

    $authorFamilyOnly = new Author('Smith');
    expect($authorFamilyOnly->fullName())->toBe('Smith');
});

it('computes_normalized_name_stripping_diacritics', function (): void {
    $author = new Author('René', 'Jean-Luc');
    // "jean-luc rené" -> "jeanluc rene"
    expect($author->normalizedFullName)->toBe('jeanluc rene');
});

it('identifies_same_person_by_orcid', function (): void {
    $orcid1 = new OrcidId('0000-0002-1825-0097');
    $orcid2 = new OrcidId('0000-0002-1825-0097');

    $a = new Author('Smith', 'John', $orcid1);
    $b = new Author('Smyth', 'Jonathan', $orcid2); // different names, same ORCID

    expect($a->isSamePerson($b))->toBeTrue();
});

it('identifies_same_person_by_normalized_name', function (): void {
    $a = new Author('O\'Connor', 'Mary'); // mary oconnor
    $b = new Author('O Connor', 'Mary');  // mary o connor -> mary o connor wait!
    // wait, preg_replace('/[^a-z\s]/', '', "mary o'connor") -> "mary oconnor"
    // preg_replace('/[^a-z\s]/', '', "mary o connor") -> "mary o connor"
    // Let's use exact expected matches
    $c = new Author('OConnor', 'Mary'); 
    
    expect($a->isSamePerson($c))->toBeTrue();
});

it('does_not_identify_different_people_as_same', function (): void {
    $a = new Author('Smith', 'John');
    $b = new Author('Smith', 'Jane');
    expect($a->isSamePerson($b))->toBeFalse();
});

it('checks_has_orcid', function (): void {
    $withOrcid = new Author('Smith', 'John', new OrcidId('0000-0000-0000-0000'));
    $without = new Author('Smith', 'John');

    expect($withOrcid->hasOrcid())->toBeTrue();
    expect($without->hasOrcid())->toBeFalse();
});

// ── AuthorList ───────────────────────────────────────────────────────────────

it('creates_empty_list_and_checks_emptiness', function (): void {
    $list = AuthorList::empty();
    expect($list->isEmpty())->toBeTrue();
    expect($list->count())->toBe(0);
    expect($list->first())->toBeNull();
    expect($list->last())->toBeNull();
    expect($list->all())->toBe([]);
});

it('creates_list_from_array_and_varargs', function (): void {
    $a1 = new Author('Smith', 'John');
    $a2 = new Author('Doe', 'Jane');

    $list1 = new AuthorList($a1, $a2);
    $list2 = AuthorList::fromArray([$a1, $a2]);

    expect($list1->count())->toBe(2);
    expect($list2->count())->toBe(2);
    expect($list1->first()->familyName)->toBe('Smith');
    expect($list1->last()->familyName)->toBe('Doe');
    expect($list1->get(1)->familyName)->toBe('Doe');
    expect($list1->get(99))->toBeNull();
});

it('intersects_two_author_lists', function (): void {
    $a1 = new Author('Smith', 'John');
    $a2 = new Author('Doe', 'Jane');
    $a3 = new Author('Brown', 'Charlie');

    $list1 = new AuthorList($a1, $a2);
    $list2 = new AuthorList($a2, $a3); // Shares Jane Doe

    $intersection = $list1->intersect($list2);

    expect($intersection->count())->toBe(1);
    expect($intersection->first()->familyName)->toBe('Doe');
});
