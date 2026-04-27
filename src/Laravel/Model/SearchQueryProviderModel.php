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
        'total_raw'       => 'integer',
        'total_unique'    => 'integer',
        'duration_ms'     => 'integer',
        'metadata'        => 'array',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQueryModel::class, 'search_query_id');
    }
}

