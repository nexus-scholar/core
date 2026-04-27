<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentSearchQuery extends Model
{
    protected $table = 'search_queries';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'               => 'string',
        'from_year'        => 'integer',
        'to_year'          => 'integer',
        'max_results'      => 'integer',
        'offset'           => 'integer',
        'include_raw_data' => 'boolean',
        'provider_aliases' => 'array',
        'total_raw'        => 'integer',
        'total_unique'     => 'integer',
        'duration_ms'      => 'integer',
        'executed_at'      => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EloquentProject::class, 'project_id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(EloquentSearchQueryProvider::class, 'search_query_id');
    }

    public function works(): HasMany
    {
        return $this->hasMany(EloquentQueryWork::class, 'search_query_id');
    }
}

