<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentProject extends Model
{
    protected $table = 'projects';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'       => 'string',
        'metadata' => 'array',
    ];

    public function works(): HasMany
    {
        return $this->hasMany(EloquentScholarlyWork::class, 'project_id');
    }

    public function searchQueries(): HasMany
    {
        return $this->hasMany(EloquentSearchQuery::class, 'project_id');
    }

    public function dedupClusters(): HasMany
    {
        return $this->hasMany(EloquentDedupCluster::class, 'project_id');
    }

    public function screeningDecisions(): HasMany
    {
        return $this->hasMany(EloquentScreeningDecision::class, 'project_id');
    }

    public function citationGraphs(): HasMany
    {
        return $this->hasMany(EloquentCitationGraph::class, 'project_id');
    }
}

