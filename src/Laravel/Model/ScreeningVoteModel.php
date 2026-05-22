<?php

declare(strict_types=1);

namespace Nexus\Laravel\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScreeningVoteModel extends Model
{
    protected $table = 'screening_votes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'id' => 'string',
        'screening_run_id' => 'string',
        'screening_decision_id' => 'string',
        'project_id' => 'string',
        'work_id' => 'string',
        'confidence' => 'float',
        'evidence' => 'array',
        'uncertainty' => 'array',
        'exclusion_basis' => 'array',
        'usage' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ScreeningRunModel::class, 'screening_run_id');
    }

    public function decision(): BelongsTo
    {
        return $this->belongsTo(ScreeningDecisionModel::class, 'screening_decision_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(ScholarlyWorkModel::class, 'work_id');
    }
}
