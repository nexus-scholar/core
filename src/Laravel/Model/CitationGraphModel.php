<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CitationGraphModel extends Model
{
    protected $table = 'citation_graphs';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'          => 'string',
        'project_id'  => 'string',
        'metadata'    => 'array',
        'node_count'  => 'integer',
        'edge_count'  => 'integer',
        'built_at'    => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SlrProject::class, 'project_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(CitationEdgeModel::class, 'graph_id');
    }
}

