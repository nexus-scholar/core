<?php

declare(strict_types=1);

it('keeps framework imports out of non-laravel bounded contexts', function (): void {
    $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'src';
    $boundedContexts = ['Shared', 'Search', 'Deduplication', 'CitationNetwork', 'Dissemination'];
    $violations = [];

    foreach ($boundedContexts as $context) {
        $path = $root . DIRECTORY_SEPARATOR . $context;
        if (! is_dir($path)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            if (str_contains($contents, 'Illuminate\\')) {
                $violations[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($violations)->toBe([]);
});
