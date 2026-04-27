<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ClusterMemberModel extends Model
{
    protected $table = 'cluster_members';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'                => 'string',
        'cluster_id'        => 'string',
        'work_id'           => 'string',
        'is_representative' => 'boolean',
        'confidence'        => 'float',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(DedupClusterModel::class, 'cluster_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}

