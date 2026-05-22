<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ScreeningRunModel extends Model
{
    protected $table = 'screening_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
        'project_id' => 'string',
        'criteria' => 'array',
        'config' => 'array',
        'source' => 'array',
        'counts' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SlrProject::class, 'project_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ScreeningDecisionModel::class, 'screening_run_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ScreeningVoteModel::class, 'screening_run_id');
    }
}
