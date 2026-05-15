<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Facades\DB;
use Nexus\Shared\Port\TransactionPort;

final class LaravelTransaction implements TransactionPort
{
    public function run(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
