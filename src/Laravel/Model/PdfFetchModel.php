<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PdfFetchModel extends Model
{
    protected $table = 'pdf_fetches';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'work_id',
        'source_alias',
        'source_url',
        'status',
        'http_status',
        'file_path',
        'duration_ms',
        'error_message',
        'attempted_at',
        'metadata',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'metadata'     => 'array',
        'http_status'  => 'integer',
        'duration_ms'  => 'integer',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}
