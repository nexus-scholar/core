<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentClusterMember extends Model
{
    protected $table = 'cluster_members';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'          => 'string',
        'is_representative' => 'boolean',
        'confidence'  => 'float',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(EloquentDedupCluster::class, 'cluster_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }
}

