<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

final class PubMedXmlParser
{
    /**
     * Parse esearch XML response for PMIDs, count, and history server params.
     *
     * @return ?array{count: int, ids: string[], webenv: string, queryKey: string}
     */
    public function parseEsearchResponse(string $xml): ?array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);

        if ($root === false) {
            return null;
        }

        $count    = (int) ((string) ($root->Count ?? '0'));
        $webenv   = (string) ($root->WebEnv ?? '');
        $queryKey = (string) ($root->QueryKey ?? '');

        $ids = [];
        if (isset($root->IdList)) {
            foreach ($root->IdList->Id as $idElem) {
                $ids[] = (string) $idElem;
            }
        }

        return [
            'count'    => $count,
            'ids'      => $ids,
            'webenv'   => $webenv,
            'queryKey' => $queryKey,
        ];
    }

    /**
     * Parse efetch XML response into ScholarlyWork array.
     *
     * @return ScholarlyWork[]
     */
    public function parseEfetchResponse(string $xml, SearchQuery $query): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);

        if ($root === false) {
            return [];
        }

        $works = [];
        foreach ($root->PubmedArticle as $articleNode) {
            $work = $this->normalizeXmlArticle($articleNode, $query);
            if ($work !== null) {
                $works[] = $work;
            }
        }

        return $works;
    }

    private function normalizeXmlArticle(\SimpleXMLElement $node, SearchQuery $query): ?ScholarlyWork
    {
        $medlineCitation = $node->MedlineCitation ?? null;
        $article         = $medlineCitation?->Article ?? null;

        if ($article === null) {
            return null;
        }

        $title = (string) ($article->ArticleTitle ?? '');
        if (trim($title) === '') {
            return null;
        }

        $ids  = WorkIdSet::empty();
        $pmid = (string) ($medlineCitation->PMID ?? '');
        if ($pmid !== '') {
            $ids = $ids->add(new WorkId(WorkIdNamespace::PUBMED, $pmid));
        }

        $doi = $this->extractDoiFromArticle($article, $node);
        if ($doi !== null) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $doi));
        }

        $abstract = $this->extractAbstract($article);
        $authors  = $this->extractAuthors($article);
        $year     = $this->extractYear($article);

        $venue     = null;
        $venueName = (string) ($article->Journal?->Title ?? '');
        if ($venueName !== '') {
            $issn = (string) ($article->Journal?->ISSN ?? '');
            $venue = new Venue(
                name: $venueName,
                issn: $issn !== '' ? $issn : null,
                type: 'journal',
            );
        }

        return ScholarlyWork::reconstitute(
            ids:            $ids,
            title:          $title,
            sourceProvider: 'pubmed',
            year:           $year,
            authors:        AuthorList::fromArray($authors),
            venue:          $venue,
            abstract:       $abstract,
            rawData:        $query->includeRawData ? $this->xmlNodeToArray($node) : null,
        );
    }

    private function extractDoiFromArticle(\SimpleXMLElement $article, \SimpleXMLElement $node): ?string
    {
        foreach ($article->ELocationID ?? [] as $eloc) {
            if ((string) ($eloc['EIdType'] ?? '') === 'doi') {
                $doiText = (string) $eloc;
                if ($doiText !== '') return $doiText;
            }
        }

        $articleIds = $node->PubmedData?->ArticleIdList ?? null;
        if ($articleIds !== null) {
            foreach ($articleIds->ArticleId as $aid) {
                if ((string) ($aid['IdType'] ?? '') === 'doi') {
                    $doiText = (string) $aid;
                    if ($doiText !== '') return $doiText;
                }
            }
        }

        return null;
    }

    private function extractAbstract(\SimpleXMLElement $article): ?string
    {
        $abstractElem = $article->Abstract ?? null;
        if ($abstractElem === null) return null;
        $parts = [];
        foreach ($abstractElem->AbstractText ?? [] as $text) {
            $content = (string) $text;
            if ($content !== '') $parts[] = $content;
        }
        $full = implode(' ', $parts);
        return $full !== '' ? $full : null;
    }

    /**
     * @return Author[]
     */
    private function extractAuthors(\SimpleXMLElement $article): array
    {
        $authorList = $article->AuthorList ?? null;
        if ($authorList === null) return [];
        $authors = [];
        foreach ($authorList->Author as $au) {
            $last = (string) ($au->LastName ?? '');
            $fore = (string) ($au->ForeName ?? '');
            if ($last === '') continue;
            $orcid = null;
            foreach ($au->Identifier ?? [] as $idNode) {
                $source = (string) ($idNode['Source'] ?? '');
                if ($source === 'ORCID') {
                    $orcidText = (string) $idNode;
                    if ($orcidText !== '') {
                        if (str_contains($orcidText, 'orcid.org/')) {
                            $orcidText = explode('orcid.org/', $orcidText)[1] ?? '';
                        }
                        if ($orcidText !== '') {
                            try { $orcid = new OrcidId($orcidText); } catch (\InvalidArgumentException) {}
                        }
                    }
                }
            }
            $authors[] = new Author(familyName: $last, givenName: $fore !== '' ? $fore : null, orcid: $orcid);
        }
        return $authors;
    }

    private function extractYear(\SimpleXMLElement $article): ?int
    {
        $pubDate = $article->Journal?->JournalIssue?->PubDate ?? null;
        if ($pubDate === null) return null;
        $yearText = (string) ($pubDate->Year ?? '');
        if ($yearText !== '') return (int) $yearText;
        $medlineDate = (string) ($pubDate->MedlineDate ?? '');
        if ($medlineDate !== '' && preg_match('/\d{4}/', $medlineDate, $matches)) {
            return (int) $matches[0];
        }
        return null;
    }

    private function xmlNodeToArray(\SimpleXMLElement $node): array
    {
        $json = json_encode($node);
        if ($json === false) return [];
        $result = json_decode($json, true);
        return is_array($result) ? $result : [];
    }
}
