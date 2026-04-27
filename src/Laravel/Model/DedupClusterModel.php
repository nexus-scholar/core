<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DedupClusterModel extends Model
{
    protected $table = 'dedup_clusters';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'                     => 'string',
        'project_id'             => 'string',
        'representative_work_id' => 'string',
        'thresholds'             => 'array',
        'metadata'               => 'array',
        'confidence'             => 'float',
        'cluster_size'           => 'integer',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SlrProject::class, 'project_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClusterMemberModel::class, 'cluster_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'representative_work_id');
    }
}

