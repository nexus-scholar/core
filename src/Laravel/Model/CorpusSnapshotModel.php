<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CorpusSnapshotModel extends Model
{
    protected $table = 'corpus_snapshots';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
        'project_id' => 'string',
        'locked_at' => 'datetime',
        'work_count' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function works(): HasMany
    {
        return $this->hasMany(CorpusSnapshotWorkModel::class, 'snapshot_id');
    }
}
