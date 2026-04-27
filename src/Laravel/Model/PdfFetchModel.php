<?php
declare(strict_types=1);
namespace Nexus\Laravel\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class PdfFetchModel extends Model {
    protected $table = 'pdf_fetches';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['id' => 'string', 'work_id' => 'string', 'metadata' => 'array', 'attempted_at' => 'datetime', 'http_status' => 'integer', 'duration_ms' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    public function work(): BelongsTo { return $this->belongsTo(ScholarlyWorkModel::class, 'work_id'); }
}