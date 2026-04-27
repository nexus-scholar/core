<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentAuthor extends Model
{
    protected $table = 'authors';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
    ];

    public function works(): HasMany
    {
        return $this->hasMany(EloquentWorkAuthor::class, 'author_id');
    }
}

