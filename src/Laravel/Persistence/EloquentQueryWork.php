<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentQueryWork extends Model
{
    protected $table = 'query_works';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'    => 'string',
        'rank'  => 'integer',
        'seen_at' => 'datetime',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(EloquentSearchQuery::class, 'search_query_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }
}

