<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentWorkAuthor extends Model
{
    protected $table = 'work_authors';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'                => 'string',
        'position'          => 'integer',
        'is_corresponding'  => 'boolean',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(EloquentAuthor::class, 'author_id');
    }
}

