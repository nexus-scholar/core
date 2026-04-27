<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CitationEdgeModel extends Model
{
    protected $table = 'citation_edges';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'             => 'string',
        'graph_id'       => 'string',
        'citing_work_id' => 'string',
        'cited_work_id'  => 'string',
        'metadata'       => 'array',
        'weight'         => 'float',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function graph(): BelongsTo
    {
        return $this->belongsTo(CitationGraphModel::class, 'graph_id');
    }

    public function citingWork(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'citing_work_id');
    }

    public function citedWork(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'cited_work_id');
    }
}

