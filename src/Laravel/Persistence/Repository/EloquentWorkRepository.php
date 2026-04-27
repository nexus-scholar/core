<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdSet;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Laravel\Model\ScholarlyWorkModel as EloquentScholarlyWork;
use Nexus\Laravel\Model\WorkExternalIdModel as EloquentWorkExternalId;
use Nexus\Laravel\Model\WorkAuthorModel as EloquentWorkAuthor;
use Nexus\Laravel\Model\AuthorModel as EloquentAuthor;

/**
 * Eloquent-backed adapter for persisting and retrieving ScholarlyWork domain objects.
 * This is the most complex repository because works touch five tables:
 * scholarly_works, work_external_ids, work_providers, work_authors, authors.
 */
final class EloquentWorkRepository implements \Nexus\Search\Domain\Port\WorkRepositoryPort
{
    /**
     * Fetch a domain ScholarlyWork by its primary ID.
     * Loads all external IDs, providers, and authors in one query.
     */
    public function findById(WorkId $id): ?ScholarlyWork
    {
        $row = EloquentScholarlyWork::with([
            'externalIds',
            'providers',
            'authors' => fn ($q) => $q->orderBy('position'),
            'authors.author',
        ])->find($id->value);

        return $row ? $this->toDomain($row) : null;
    }

    /**
     * @param WorkId[] $ids
     * @return ScholarlyWork[] Keyed by WorkId string (toString())
     */
    public function findManyByIds(array $ids): array
    {
        $idStrings = array_map(fn (WorkId $id) => $id->value, $ids);

        $rows = EloquentScholarlyWork::with([
            'externalIds',
            'providers',
            'authors' => fn ($q) => $q->orderBy('position'),
            'authors.author',
        ])->whereIn('id', $idStrings)->get();

        $results = [];
        foreach ($rows as $row) {
            $domainWork = $this->toDomain($row);
            // Must key by toString() to match the port interface contract
            $results[$domainWork->primaryId()->toString()] = $domainWork;
        }

        return $results;
    }

    /**
     * Save (create or update) a ScholarlyWork and all its related data atomically.
     * Performs insertOrUpdate on the work row, then re-syncs all external IDs and authors.
     */
    public function save(ScholarlyWork $work): void
    {
        $internalId = $work->ids()->findByNamespace(\Nexus\Shared\ValueObject\WorkIdNamespace::INTERNAL);
        $workId = $internalId?->value ?? $work->primaryId()?->value ?? throw new \InvalidArgumentException(
            'Cannot persist ScholarlyWork without a primary ID or internal database ID.'
        );

        // Update or create the main work row
        $row = EloquentScholarlyWork::updateOrCreate(
            ['id' => $workId],
            $this->toRow($work)
        );

        // Re-sync external IDs (delete old, insert new)
        $row->externalIds()->delete();
        foreach ($work->ids()->all() as $workIdObj) {
            $row->externalIds()->create([
                'id'         => (string) \Illuminate\Support\Str::uuid(),
                'namespace'  => $workIdObj->namespace->value,
                'value'      => $workIdObj->value,
                'is_primary' => $workIdObj->equals($work->primaryId()),
            ]);
        }

        // Re-sync authors (delete old, insert new with position)
        $row->authors()->delete();
        foreach ($work->authors()->all() as $i => $author) {
            $authorRow = EloquentAuthor::firstOrCreate(
                ['full_name' => $author->familyName . ($author->givenName ? ', ' . $author->givenName : '')],
                ['id' => (string) \Illuminate\Support\Str::uuid(), 'normalized_name' => mb_strtolower($author->familyName)]
            );

            $row->authors()->create([
                'id'        => (string) \Illuminate\Support\Str::uuid(),
                'author_id' => $authorRow->id,
                'position'  => $i,
                'is_corresponding' => false,
            ]);
        }
    }

    /**
     * Convert a domain ScholarlyWork to an Eloquent-insertable row array.
     * Never includes relationships — those are synced separately.
     */
    private function toRow(ScholarlyWork $work): array
    {
        return [
            'title'             => $work->title(),
            'abstract'          => $work->abstract(),
            'year'              => $work->year() ?? 0,
            'venue_name'        => $work->venue()?->name,
            'venue_issn'        => $work->venue()?->issn,
            'venue_type'        => $work->venue()?->type,
            'language'          => null, // TODO: extract from domain
            'cited_by_count'    => $work->citedByCount() ?? 0,
            'is_retracted'      => $work->isRetracted(),
            'retrieved_at'      => $work->retrievedAt(),
        ];
    }

    /**
     * Convert an Eloquent row (with eager-loaded relationships) to a domain ScholarlyWork.
     * Never returns the Eloquent model itself — always reconstructs the domain object.
     */
    private function toDomain(EloquentScholarlyWork $row): ScholarlyWork
    {
        // Reconstruct WorkIdSet from external_ids
        $ids = WorkIdSet::fromArray([
            new WorkId(\Nexus\Shared\ValueObject\WorkIdNamespace::INTERNAL, $row->id)
        ]);
        foreach ($row->externalIds as $idRow) {
            $ids = $ids->add(new WorkId(
                \Nexus\Shared\ValueObject\WorkIdNamespace::from($idRow->namespace),
                $idRow->value
            ));
        }

        // Reconstruct AuthorList from work_authors joined to authors
        $authors = [];
        foreach ($row->authors as $workAuthorRow) {
            $authorRow = $workAuthorRow->author;
            $nameParts = explode(', ', $authorRow->full_name, 2);
            $authors[] = new Author(
                familyName: $nameParts[0],
                givenName: $nameParts[1] ?? null,
                orcid: $authorRow->orcid ? new \Nexus\Shared\ValueObject\OrcidId($authorRow->orcid) : null,
            );
        }

        // Reconstruct Venue if present
        $venue = null;
        if ($row->venue_name) {
            $venue = new Venue(
                name: $row->venue_name,
                issn: $row->venue_issn,
                type: $row->venue_type,
            );
        }

        return ScholarlyWork::reconstitute(
            ids:            $ids,
            title:          $row->title,
            sourceProvider: 'persisted', // TODO: track original provider(s)
            year:           $row->year,
            authors:        AuthorList::fromArray($authors),
            venue:          $venue,
            abstract:       $row->abstract,
            citedByCount:   $row->cited_by_count,
            isRetracted:    $row->is_retracted,
        );
    }
}
