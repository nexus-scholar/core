<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentCitationGraph extends Model
{
    protected $table = 'citation_graphs';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'        => 'string',
        'node_count' => 'integer',
        'edge_count' => 'integer',
        'metadata'  => 'array',
        'built_at'  => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EloquentProject::class, 'project_id');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(EloquentCitationEdge::class, 'graph_id');
    }
}

