<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentPdfFetch extends Model
{
    protected $table = 'pdf_fetches';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'          => 'string',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'attempted_at' => 'datetime',
        'metadata'    => 'array',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }
}

