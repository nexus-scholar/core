<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CorpusSnapshotWorkModel extends Model
{
    protected $table = 'corpus_snapshot_works';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
        'snapshot_id' => 'string',
        'work_id' => 'string',
        'search_query_ids' => 'array',
        'provider_aliases' => 'array',
        'provenance' => 'array',
        'included_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(CorpusSnapshotModel::class, 'snapshot_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}
