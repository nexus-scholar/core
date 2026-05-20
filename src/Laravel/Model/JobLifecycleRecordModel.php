<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;

final class JobLifecycleRecordModel extends Model
{
    protected $table = 'job_lifecycle_records';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'idempotency_key',
        'run_id',
        'job_name',
        'job_class',
        'status',
        'project_id',
        'work_id',
        'context',
        'summary',
        'error_class',
        'error_message',
        'duration_ms',
        'occurred_at',
    ];

    protected $casts = [
        'id'          => 'string',
        'context'     => 'array',
        'summary'     => 'array',
        'duration_ms' => 'integer',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
