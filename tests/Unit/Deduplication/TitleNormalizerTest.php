<?php

declare(strict_types=1);

use Nexus\Deduplication\Infrastructure\TitleNormalizer;

$normalizer = new TitleNormalizer();

// ── TitleNormalizer::normalize ────────────────────────────────────────────────

it('lowercases_and_strips_diacritics', function () use ($normalizer): void {
    $result = $normalizer->normalize('Héllo Wörld');
    expect($result)->toBe('hello world');
});

it('strips_html_entities', function () use ($normalizer): void {
    $result = $normalizer->normalize('Deep &amp; Machine Learning');
    expect($result)->not->toContain('&amp;');
    expect($result)->toContain('deep');
    expect($result)->toContain('machine');
    expect($result)->toContain('learning');
});

it('handles_arabic_title_without_error', function () use ($normalizer): void {
    $result = $normalizer->normalize('التعلم الآلي');
    expect($result)->toBeString(); // must not throw
});

it('handles_chinese_title_without_error', function () use ($normalizer): void {
    $result = $normalizer->normalize('深度学习与自然语言处理');
    expect($result)->toBeString();
});

// ── TitleNormalizer::fuzzyRatio ───────────────────────────────────────────────

it('computes_100_ratio_for_identical_strings', function () use ($normalizer): void {
    expect($normalizer->fuzzyRatio('Machine Learning', 'Machine Learning'))->toBe(100);
});

it('computes_0_ratio_for_completely_different_strings', function () use ($normalizer): void {
    // "aaa" vs "bbb" — after normalization both are non-empty and fully different
    $ratio = $normalizer->fuzzyRatio('aaaaaaaaaa', 'bbbbbbbbbb');
    expect($ratio)->toBeLessThan(30);
});

it('computes_high_ratio_for_near_identical_titles', function () use ($normalizer): void {
    $a = 'Deep Learning for Natural Language Processing';
    $b = 'Deep Learning for Natural Language Procesing'; // one char typo
    expect($normalizer->fuzzyRatio($a, $b))->toBeGreaterThan(92);
});

it('is_not_byte_count_based', function () use ($normalizer): void {
    // "café" — 5 chars in Unicode, 6 bytes in UTF-8 (é = 2 bytes)
    // After normalization: "cafe" (4 chars)
    // If fuzzyRatio used strlen, byte count would be wrong
    $ratio = $normalizer->fuzzyRatio('café au lait', 'cafe au lait');
    expect($ratio)->toBe(100); // normalized to same string
});
