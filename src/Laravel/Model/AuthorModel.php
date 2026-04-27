<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AuthorModel extends Model
{
    protected $table = 'authors';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'             => 'string',
        'external_ids'   => 'array',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function workAuthors(): HasMany
    {
        return $this->hasMany(WorkAuthorModel::class, 'author_id');
    }
}

