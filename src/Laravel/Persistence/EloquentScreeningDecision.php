<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EloquentScreeningDecision extends Model
{
    protected $table = 'screening_decisions';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'        => 'string',
        'decided_at' => 'datetime',
        'metadata'  => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EloquentProject::class, 'project_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(EloquentScholarlyWork::class, 'work_id');
    }
}

