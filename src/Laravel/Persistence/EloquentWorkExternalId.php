<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentWorkExternalId extends Model
{
    protected $table = 'work_external_ids';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'         => 'string',
        'is_primary' => 'boolean',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }
}

