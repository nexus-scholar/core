<?php

declare(strict_types=1);

it('does not keep empty php placeholders in source or test directories', function (): void {
    $root = dirname(__DIR__, 3);
    $directories = [
        $root . DIRECTORY_SEPARATOR . 'src',
        $root . DIRECTORY_SEPARATOR . 'tests',
    ];
    $emptyFiles = [];

    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = trim(file_get_contents($file->getPathname()) ?: '');

            if ($contents === "<?php\n\ndeclare(strict_types=1);" || $contents === "<?php\r\n\r\ndeclare(strict_types=1);") {
                $emptyFiles[] = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }
    }

    expect($emptyFiles)->toBe([]);
});
