<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Nexus\Laravel\Command\NexusSearchCommand;

it('registers the reusable nexus search command', function (): void {
    $commands = app(Kernel::class)->all();

    expect($commands)->toHaveKey('nexus:search')
        ->and($commands['nexus:search'])->toBeInstanceOf(NexusSearchCommand::class);
});

it('does not register planned or removed nexus commands', function (): void {
    $commands = array_keys(app(Kernel::class)->all());

    expect($commands)->not->toContain(
        'nexus:dedup',
        'nexus:snowball',
        'nexus:fetch-pdf',
        'nexus:export',
    );
});
