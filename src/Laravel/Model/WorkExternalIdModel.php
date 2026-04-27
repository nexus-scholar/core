<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkExternalIdModel extends Model
{
    protected $table = 'work_external_ids';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'         => 'string',
        'work_id'    => 'string',
        'is_primary' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}

