<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkProviderModel extends Model
{
    protected $table = 'work_providers';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'             => 'string',
        'work_id'        => 'string',
        'metadata'       => 'array',
        'first_seen_at'  => 'datetime',
        'last_seen_at'   => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}

