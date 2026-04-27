<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SearchQueryProviderModel extends Model
{
    protected $table = 'search_query_providers';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'              => 'string',
        'search_query_id' => 'string',
        'result_count'    => 'integer',
        'latency_ms'      => 'integer',
        'metadata'        => 'array',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQueryModel::class, 'search_query_id');
    }
}

