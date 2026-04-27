<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SearchQueryModel extends Model
{
    protected $table = 'search_queries';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'               => 'string',
        'project_id'       => 'string',
        'provider_aliases' => 'array',
        'metadata'         => 'array',
        'include_raw_data' => 'boolean',
        'total_raw'        => 'integer',
        'total_unique'     => 'integer',
        'duration_ms'      => 'integer',
        'executed_at'      => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SlrProject::class, 'project_id');
    }

    public function providerProgress(): HasMany
    {
        return $this->hasMany(SearchQueryProviderModel::class, 'search_query_id');
    }

    public function queryWorks(): HasMany
    {
        return $this->hasMany(QueryWorkModel::class, 'search_query_id');
    }
}

