<?php
declare(strict_types=1);
namespace Nexus\Laravel\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class QueryWorkModel extends Model {
    protected $table = 'query_works';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['id' => 'string', 'search_query_id' => 'string', 'work_id' => 'string', 'rank' => 'integer', 'seen_at' => 'datetime', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
    public function searchQuery(): BelongsTo { return $this->belongsTo(SearchQueryModel::class, 'search_query_id'); }
    public function work(): BelongsTo { return $this->belongsTo(ScholarlyWorkModel::class, 'work_id'); }
}