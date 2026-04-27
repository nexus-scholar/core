<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentCitationEdge extends Model
{
    protected $table = 'citation_edges';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'     => 'string',
        'weight' => 'float',
        'metadata' => 'array',
    ];

    public function graph(): BelongsTo
    {
        return $this->belongsTo(EloquentCitationGraph::class, 'graph_id');
    }

    public function citingWork(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'citing_work_id');
    }

    public function citedWork(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'cited_work_id');
    }
}

