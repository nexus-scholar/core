<?php
declare(strict_types=1);
namespace Nexus\Laravel\Persistence\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\CitationGraphModel;
use Nexus\Laravel\Model\CitationEdgeModel;
final class EloquentCitationGraphRepository {
    public function save(array \, array \): void {
        DB::transaction(function () use (\, \): void {
            \ = CitationGraphModel::updateOrCreate(['id' => \['id']], \);
            CitationEdgeModel::where('graph_id', \->id)->delete();
            foreach (\ as \) { CitationEdgeModel::create(array_merge(['id' => (string) Str::uuid()], \)); }
        });
    }
    public function findById(string \): ?CitationGraphModel {
        return CitationGraphModel::with('edges')->find(\);
    }
    public function findByProject(string \): array {
        return CitationGraphModel::with('edges')->where('project_id', \)->orderByDesc('built_at')->get()->all();
    }
}
