<?php

declare(strict_types=1);

it('keeps shared work and corpus models out of search namespace imports in other source contexts', function (): void {
    $root = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'src';
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

        if (str_starts_with($relativePath, 'Search'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';

        if (str_contains($contents, 'Nexus\\Search\\Domain\\ScholarlyWork')
            || str_contains($contents, 'Nexus\\Search\\Domain\\CorpusSlice')) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});

it('keeps unsafe corpus construction out of production code', function (): void {
    $root = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'src';
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());

        if ($relativePath === 'Shared'.DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR.'CorpusSlice.php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';

        if (str_contains($contents, 'fromWorksUnsafe(')) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});

it('keeps provider integration tests fixture backed with no live-capable http clients', function (): void {
    $root = dirname(__DIR__, 3);
    $providerTests = $root.DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR.'Integration'.DIRECTORY_SEPARATOR.'Provider';
    $blockedPatterns = [
        'VCR\\',
        'VCR::',
        'GuzzleHttpClient',
        'GuzzleHttpClient::create(',
        'enableLibraryHooks',
        'stream_wrapper',
        'curl_init',
        'CURLOPT_',
        'new Client(',
    ];
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($providerTests));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';

        foreach ($blockedPatterns as $pattern) {
            if (str_contains($contents, $pattern)) {
                $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname()).' contains '.$pattern;
            }
        }
    }

    expect($violations)->toBe([]);
});

it('does not keep runtime logs under package storage', function (): void {
    $root = dirname(__DIR__, 3);
    $storage = $root.DIRECTORY_SEPARATOR.'storage';
    $logs = [];

    if (is_dir($storage)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'log') {
                $logs[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($logs)->toBe([]);
});
