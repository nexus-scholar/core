<?php

require 'vendor/autoload.php';

$app = \Orchestra\Testbench\Foundation\Application::create(
    basePath: __DIR__,
    options: ['extra' => ['providers' => [\Nexus\Laravel\NexusServiceProvider::class]]]
);

$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('nexus:search', [
    'query' => '"Segment Anything" AND (agriculture OR "plant phenotyping" OR greenhouse OR tomato OR fruit OR leaf OR crop)',
    '--from-year' => '2024',
    '--to-year' => '2026',
    '--max' => '50'
]);

echo $app->make(\Illuminate\Contracts\Console\Kernel::class)->output();
