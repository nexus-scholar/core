<?php

declare(strict_types=1);

use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\CorpusSliceId;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\Domain\CorpusSlice as SharedCorpusSlice;
use Nexus\Shared\Domain\CorpusSliceId as SharedCorpusSliceId;
use Nexus\Shared\Domain\ScholarlyWork as SharedScholarlyWork;

it('keeps deprecated search domain aliases for shared work models', function (): void {
    expect(class_exists(ScholarlyWork::class))->toBeTrue()
        ->and(class_exists(CorpusSlice::class))->toBeTrue()
        ->and(class_exists(CorpusSliceId::class))->toBeTrue()
        ->and(is_a(ScholarlyWork::class, SharedScholarlyWork::class, true))->toBeTrue()
        ->and(is_a(CorpusSlice::class, SharedCorpusSlice::class, true))->toBeTrue()
        ->and(is_a(CorpusSliceId::class, SharedCorpusSliceId::class, true))->toBeTrue();
});
