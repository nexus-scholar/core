<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EloquentScholarlyWork extends Model
{
    protected $table = 'scholarly_works';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'                => 'string',
        'year'              => 'integer',
        'cited_by_count'    => 'integer',
        'is_retracted'      => 'boolean',
        'retrieved_at'      => 'datetime',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(EloquentProject::class, 'project_id');
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(EloquentWorkExternalId::class, 'work_id');
    }

    public function providers(): HasMany
    {
        return $this->hasMany(EloquentWorkProvider::class, 'work_id');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(EloquentWorkAuthor::class, 'work_id');
    }

    public function clusterMembers(): HasMany
    {
        return $this->hasMany(EloquentClusterMember::class, 'work_id');
    }

    public function screeningDecisions(): HasMany
    {
        return $this->hasMany(EloquentScreeningDecision::class, 'work_id');
    }

    public function pdfFetches(): HasMany
    {
        return $this->hasMany(EloquentPdfFetch::class, 'work_id');
    }
}

