<?php

declare(strict_types=1);

use Nexus\Dissemination\Domain\FullText;
use Nexus\Dissemination\Domain\FullTextStatus;

it('creates_success_full_text', function (): void {
    $fullText = FullText::success('pdfs/test.pdf', 'example', 200);

    expect($fullText->status)->toBe(FullTextStatus::SUCCESS);
    expect($fullText->filePath)->toBe('pdfs/test.pdf');
    expect($fullText->sourceAlias)->toBe('example');
    expect($fullText->httpStatus)->toBe(200);
    expect($fullText->isSuccess())->toBeTrue();
});

it('creates_failure_full_text', function (): void {
    $fullText = FullText::failure('Not found', 'example', 404);

    expect($fullText->status)->toBe(FullTextStatus::FAILURE);
    expect($fullText->errorMessage)->toBe('Not found');
    expect($fullText->sourceAlias)->toBe('example');
    expect($fullText->httpStatus)->toBe(404);
    expect($fullText->isSuccess())->toBeFalse();
});

it('creates_skipped_full_text', function (): void {
    $fullText = FullText::skipped('No ID');

    expect($fullText->status)->toBe(FullTextStatus::SKIPPED);
    expect($fullText->errorMessage)->toBe('No ID');
    expect($fullText->isSuccess())->toBeFalse();
});

