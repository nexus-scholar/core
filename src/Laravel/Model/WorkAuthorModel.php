<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkAuthorModel extends Model
{
    protected $table = 'work_authors';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'         => 'string',
        'work_id'    => 'string',
        'author_id'  => 'string',
        'position'   => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(AuthorModel::class, 'author_id');
    }
}

