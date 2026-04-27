<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentDedupCluster extends Model
{
    protected $table = 'dedup_clusters';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'         => 'string',
        'thresholds' => 'array',
        'cluster_size' => 'integer',
        'confidence' => 'float',
        'metadata'   => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EloquentProject::class, 'project_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'representative_work_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(EloquentClusterMember::class, 'cluster_id');
    }
}

