<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SlrProject extends Model
{
    protected $table = 'projects';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'         => 'string',
        'settings'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function searchQueries(): HasMany
    {
        return $this->hasMany(SearchQueryModel::class, 'project_id');
    }

    public function works(): HasMany
    {
        return $this->hasMany(ScholarlyWorkModel::class, 'project_id');
    }

    public function dedupClusters(): HasMany
    {
        return $this->hasMany(DedupClusterModel::class, 'project_id');
    }

    public function citationGraphs(): HasMany
    {
        return $this->hasMany(CitationGraphModel::class, 'project_id');
    }
}

