<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentSearchQueryProvider extends Model
{
    protected $table = 'search_query_providers';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'          => 'string',
        'result_count' => 'integer',
        'latency_ms'  => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(EloquentSearchQuery::class, 'search_query_id');
    }
}

