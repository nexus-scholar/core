<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScholarlyWorkModel extends Model
{
    protected $table = 'scholarly_works';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    protected $casts = [
        'id'           => 'string',
        'project_id'   => 'string',
        'is_retracted' => 'boolean',
        'retrieved_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SlrProject::class, 'project_id');
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(WorkExternalIdModel::class, 'work_id');
    }

    public function providerSightings(): HasMany
    {
        return $this->hasMany(WorkProviderModel::class, 'work_id');
    }

    public function workAuthors(): HasMany
    {
        return $this->hasMany(WorkAuthorModel::class, 'work_id');
    }

    public function clusterMemberships(): HasMany
    {
        return $this->hasMany(ClusterMemberModel::class, 'work_id');
    }

    public function screeningDecisions(): HasMany
    {
        return $this->hasMany(ScreeningDecisionModel::class, 'work_id');
    }

    public function pdfFetches(): HasMany
    {
        return $this->hasMany(PdfFetchModel::class, 'work_id');
    }
}

