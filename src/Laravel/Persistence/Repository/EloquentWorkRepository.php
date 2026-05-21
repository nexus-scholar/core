<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Nexus\Shared\ValueObject\Venue;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\ScholarlyWorkModel as EloquentScholarlyWork;
use Nexus\Laravel\Model\WorkExternalIdModel as EloquentWorkExternalId;
use Nexus\Laravel\Model\WorkAuthorModel as EloquentWorkAuthor;
use Nexus\Laravel\Model\AuthorModel as EloquentAuthor;
use Nexus\Laravel\Model\WorkProviderModel as EloquentWorkProvider;

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
        $row = $this->findRowByWorkId($id);

        return $row ? $this->toDomain($row) : null;
    }

    /**
     * @param WorkId[] $ids
     * @return ScholarlyWork[] Keyed by WorkId string (toString())
     */
    public function findManyByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $internalIds = [];
        $externalNamespaces = [];
        $externalValues = [];

        foreach ($ids as $id) {
            if ($id->namespace === WorkIdNamespace::INTERNAL) {
                $internalIds[] = $id->value;
                continue;
            }

            $externalNamespaces[] = $id->namespace->value;
            $externalValues[] = $id->value;
        }

        $externalRows = collect();

        if ($externalNamespaces !== [] && $externalValues !== []) {
            $externalRows = EloquentWorkExternalId::query()
                ->whereIn('namespace', array_unique($externalNamespaces))
                ->whereIn('value', array_unique($externalValues))
                ->get(['work_id', 'namespace', 'value']);
        }

        $externalToInternal = [];
        foreach ($externalRows as $externalRow) {
            $externalToInternal[$externalRow->namespace . ':' . $externalRow->value] = $externalRow->work_id;
        }

        $rows = EloquentScholarlyWork::with([
            'externalIds',
            'providers',
            'authors' => fn ($q) => $q->orderBy('position'),
            'authors.author',
        ])->whereIn('id', array_values(array_unique([
            ...$internalIds,
            ...array_values($externalToInternal),
        ])))->get()->keyBy('id');

        $results = [];
        foreach ($ids as $id) {
            $internalId = $id->namespace === WorkIdNamespace::INTERNAL
                ? $id->value
                : ($externalToInternal[$id->toString()] ?? null);

            if ($internalId === null || ! isset($rows[$internalId])) {
                continue;
            }

            $results[$id->toString()] = $this->toDomain($rows[$internalId]);
        }

        return $results;
    }

    /**
     * Save (create or update) a ScholarlyWork and all its related data atomically.
     * Performs insertOrUpdate on the work row, then re-syncs all external IDs and authors.
     */
    public function save(ScholarlyWork $work): void
    {
        $existingRow = $this->findExistingRowFor($work);
        $workId = $existingRow?->id ?? (string) Str::uuid();

        // Update or create the main work row
        $row = EloquentScholarlyWork::updateOrCreate(
            ['id' => $workId],
            $this->toRow($work)
        );

        // Re-sync external IDs (delete old, insert new)
        $row->externalIds()->delete();
        foreach ($work->ids()->all() as $workIdObj) {
            if ($workIdObj->namespace === WorkIdNamespace::INTERNAL) {
                continue;
            }

            $row->externalIds()->create([
                'id'         => (string) Str::uuid(),
                'namespace'  => $workIdObj->namespace->value,
                'value'      => $workIdObj->value,
                'is_primary' => $workIdObj->equals($work->primaryId()),
            ]);
        }

        $this->recordProvider($row, $work);

        // Re-sync authors (delete old, insert new with position)
        $row->authors()->delete();
        $position = 0;
        $seenAuthorIds = [];

        foreach ($this->uniqueAuthors($work->authors()) as $author) {
            $authorRow = $this->findOrCreateAuthor($author);

            if (isset($seenAuthorIds[$authorRow->id])) {
                continue;
            }

            $seenAuthorIds[$authorRow->id] = true;
            $row->authors()->create([
                'id'        => (string) Str::uuid(),
                'author_id' => $authorRow->id,
                'position'  => $position++,
                'is_corresponding' => false,
            ]);
        }
    }

    /**
     * @return Author[]
     */
    private function uniqueAuthors(AuthorList $authors): array
    {
        $unique = [];
        $seen = [];

        foreach ($authors->all() as $author) {
            $key = $this->authorIdentityKey($author);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $author;
        }

        return $unique;
    }

    private function authorIdentityKey(Author $author): string
    {
        if ($author->orcid !== null) {
            return 'orcid:' . $author->orcid->toString();
        }

        return 'name:' . $author->normalizedFullName;
    }

    private function findOrCreateAuthor(Author $author): EloquentAuthor
    {
        $fullName = $author->familyName . ($author->givenName ? ', ' . $author->givenName : '');
        $orcid = $author->orcid?->toString();

        if ($orcid !== null) {
            return EloquentAuthor::firstOrCreate(
                ['orcid' => $orcid],
                [
                    'id' => (string) Str::uuid(),
                    'full_name' => $fullName,
                    'normalized_name' => $author->normalizedFullName,
                ]
            );
        }

        return EloquentAuthor::firstOrCreate(
            ['full_name' => $fullName],
            [
                'id' => (string) Str::uuid(),
                'normalized_name' => $author->normalizedFullName,
            ]
        );
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
            'language'          => null,
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
            new WorkId(WorkIdNamespace::INTERNAL, $row->id)
        ]);
        foreach ($row->externalIds as $idRow) {
            $ids = $ids->add(new WorkId(
                WorkIdNamespace::from($idRow->namespace),
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
            sourceProvider: $this->sourceProviderFor($row),
            year:           $row->year,
            authors:        AuthorList::fromArray($authors),
            venue:          $venue,
            abstract:       $row->abstract,
            citedByCount:   $row->cited_by_count,
            isRetracted:    $row->is_retracted,
        );
    }

    private function findRowByWorkId(WorkId $id): ?EloquentScholarlyWork
    {
        $query = EloquentScholarlyWork::with([
            'externalIds',
            'providers',
            'authors' => fn ($q) => $q->orderBy('position'),
            'authors.author',
        ]);

        if ($id->namespace === WorkIdNamespace::INTERNAL) {
            return $query->find($id->value);
        }

        $externalId = EloquentWorkExternalId::query()
            ->where('namespace', $id->namespace->value)
            ->where('value', $id->value)
            ->first();

        return $externalId ? $query->find($externalId->work_id) : null;
    }

    private function findExistingRowFor(ScholarlyWork $work): ?EloquentScholarlyWork
    {
        $internalId = $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL);

        if ($internalId !== null) {
            $row = EloquentScholarlyWork::find($internalId->value);

            if ($row !== null) {
                return $row;
            }
        }

        foreach ($work->ids()->all() as $id) {
            if ($id->namespace === WorkIdNamespace::INTERNAL) {
                continue;
            }

            $externalId = EloquentWorkExternalId::query()
                ->where('namespace', $id->namespace->value)
                ->where('value', $id->value)
                ->first();

            if ($externalId !== null) {
                return EloquentScholarlyWork::find($externalId->work_id);
            }
        }

        return null;
    }

    private function recordProvider(EloquentScholarlyWork $row, ScholarlyWork $work): void
    {
        $providerAlias = trim($work->sourceProvider());

        if ($providerAlias === '') {
            return;
        }

        /** @var EloquentWorkProvider $provider */
        $provider = $row->providers()->firstOrNew(['provider_alias' => $providerAlias]);

        if (! $provider->exists) {
            $provider->id = (string) Str::uuid();
            $provider->first_seen_at = now();
        }

        $provider->provider_work_id = $this->providerWorkId($work, $providerAlias);
        $provider->last_seen_at = now();
        $provider->save();
    }

    private function providerWorkId(ScholarlyWork $work, string $providerAlias): ?string
    {
        $namespace = $this->namespaceForProvider($providerAlias);
        $providerId = $namespace ? $work->ids()->findByNamespace($namespace) : null;

        return $providerId?->value ?? $work->primaryId()?->value;
    }

    private function namespaceForProvider(string $providerAlias): ?WorkIdNamespace
    {
        return match ($providerAlias) {
            'arxiv' => WorkIdNamespace::ARXIV,
            'crossref' => WorkIdNamespace::DOI,
            'doaj' => WorkIdNamespace::DOAJ,
            'ieee' => WorkIdNamespace::IEEE,
            'openalex' => WorkIdNamespace::OPENALEX,
            'pubmed' => WorkIdNamespace::PUBMED,
            'semantic_scholar' => WorkIdNamespace::S2,
            default => null,
        };
    }

    private function sourceProviderFor(EloquentScholarlyWork $row): string
    {
        $provider = $row->providers
            ->sortByDesc(fn ($provider) => $provider->last_seen_at?->getTimestamp() ?? 0)
            ->first();

        return $provider?->provider_alias ?? 'persisted';
    }
}
