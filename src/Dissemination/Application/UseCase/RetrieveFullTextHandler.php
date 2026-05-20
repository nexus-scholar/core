<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use DateTimeImmutable;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\FullTextArtifactType;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Shared\ValueObject\WorkId;
use RuntimeException;
use Throwable;

final readonly class RetrieveFullTextHandler
{
    public function __construct(
        private FullTextSourceCollection $sources,
        private FileStoragePort          $storage,
        private PdfDownloaderPort        $downloader,
        private PdfFetchRepositoryPort   $repository,
    ) {}

    public function handle(RetrieveFullText $command): FullTextResult
    {
        $workId = $command->work->primaryId();
        if ($workId === null) {
            return FullTextResult::skipped("Work has no primary ID");
        }

        // 1. Check if already successfully fetched
        $existingPath = $this->repository->findSuccessfulPath($workId);
        if ($existingPath !== null && $this->storage->exists($existingPath)) {
            return FullTextResult::success($existingPath, 'cache');
        }

        $lastResult = null;

        foreach ($this->sources->all() as $source) {
            if (! $source->supports($command->work)) {
                continue;
            }

            $startTime = hrtime(true);
            $candidate = null;

            try {
                $candidate = $this->candidateFor($source, $command->work);
                $url = $candidate?->url;

                if ($url === null) {
                    continue;
                }

                $metadata = $this->metadataFor($candidate);

                if ($this->isInFailedAttemptCooldown($workId, $url, $command)) {
                    continue;
                }

                $downloadResult = $this->downloadWithRetry($url, $command);
                $this->assertValidArtifact($candidate->artifactType, $downloadResult, $command->maxBytes);
                $content = $downloadResult->content;
                
                $fullPath = $this->storagePathFor($command, $workId, $source->alias(), $candidate->artifactType);
                $storedPath = $this->storage->store($fullPath, $content);
                $metadata = $this->metadataWithStoredArtifacts(
                    $metadata,
                    $candidate,
                    $command,
                    $workId,
                    $source->alias(),
                    $content,
                );

                $result = FullTextResult::success(
                    $storedPath,
                    $source->alias(),
                    $downloadResult->statusCode,
                    $metadata,
                );
                $this->repository->save($workId, $url, $result, $this->elapsedMs($startTime));

                return $result;
            } catch (Throwable $e) {
                $status = method_exists($e, 'getCode') ? $e->getCode() : null;
                $metadata = $candidate instanceof FullTextSourceCandidate ? $this->metadataFor($candidate) : [];
                $result = FullTextResult::failure($e->getMessage(), $source->alias(), (int) $status, $metadata);
                $this->repository->save($workId, $candidate?->url ?? 'unknown', $result, $this->elapsedMs($startTime));
                $lastResult = $result;
                // Continue to next source
            }
        }

        return $lastResult ?? FullTextResult::failure("No full-text artifact found across all sources");
    }

    private function candidateFor(FullTextSourcePort $source, \Nexus\Search\Domain\ScholarlyWork $work): ?FullTextSourceCandidate
    {
        if ($source instanceof FullTextCandidateSourcePort) {
            return $source->resolveCandidate($work);
        }

        $url = $source->resolve($work);

        return $url === null ? null : FullTextSourceCandidate::pdf($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(FullTextSourceCandidate $candidate): array
    {
        return array_filter(
            [
                'artifact_type' => $candidate->artifactType->value,
                ...$candidate->metadata,
            ],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    private function isInFailedAttemptCooldown(
        WorkId $workId,
        string $url,
        RetrieveFullText $command,
    ): bool {
        if ($command->failedAttemptCooldownSeconds === 0) {
            return false;
        }

        $since = new DateTimeImmutable(sprintf('-%d seconds', $command->failedAttemptCooldownSeconds));

        return $this->repository->hasRecentFailure($workId, $url, $since);
    }

    private function downloadWithRetry(string $url, RetrieveFullText $command): DownloadResult
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $command->maxDownloadAttempts; $attempt++) {
            try {
                return $this->downloader->download($url);
            } catch (Throwable $error) {
                $lastError = $error;
            }
        }

        throw $lastError ?? new RuntimeException('Failed to download full-text artifact.');
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }

    private function storagePathFor(
        RetrieveFullText $command,
        WorkId $workId,
        string $sourceAlias,
        FullTextArtifactType $artifactType = FullTextArtifactType::PDF,
    ): string
    {
        $folder = $this->safeFolder($command->destinationFolder);
        $workSegment = $this->safePathSegment($workId->toString(), 80);
        $sourceSegment = $this->safePathSegment($sourceAlias, 40);
        $hash = substr(hash('sha256', $workId->toString() . '|' . $sourceAlias), 0, 16);
        $filename = sprintf('%s_%s_%s.%s', $workSegment, $sourceSegment, $hash, $this->extensionFor($artifactType));

        return $folder === '' ? $filename : $folder . '/' . $filename;
    }

    private function safeFolder(string $folder): string
    {
        $segments = preg_split('#[\\\\/]+#', $folder) ?: [];
        $safe = [];

        foreach ($segments as $segment) {
            if (in_array(trim($segment), ['', '.', '..'], true)) {
                continue;
            }

            $clean = $this->safePathSegment($segment, 80);
            $safe[] = $clean;
        }

        return implode('/', $safe);
    }

    private function safePathSegment(string $value, int $maxLength): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value)) ?? '';
        $safe = trim($safe, '._-');

        if ($safe === '' || $safe === '.' || $safe === '..') {
            return 'file';
        }

        return substr($safe, 0, $maxLength);
    }

    private function assertValidArtifact(
        FullTextArtifactType $artifactType,
        DownloadResult $downloadResult,
        int $maxBytes,
    ): void {
        match ($artifactType) {
            FullTextArtifactType::PDF => $this->assertValidPdf($downloadResult, $maxBytes),
            FullTextArtifactType::XML => $this->assertValidXml($downloadResult, $maxBytes),
            FullTextArtifactType::TEXT => $this->assertValidText($downloadResult, $maxBytes),
        };
    }

    private function assertValidPdf(DownloadResult $downloadResult, int $maxBytes): void
    {
        if (strlen($downloadResult->content) > $maxBytes) {
            throw new RuntimeException(
                sprintf('Downloaded PDF exceeds size limit of %d bytes.', $maxBytes),
                $downloadResult->statusCode,
            );
        }

        if (! str_starts_with($downloadResult->content, '%PDF-')) {
            throw new RuntimeException('Downloaded content is not a PDF: missing %PDF signature.', $downloadResult->statusCode);
        }

        if ($downloadResult->contentType === null || $downloadResult->contentType === '') {
            return;
        }

        $mediaType = strtolower(trim(strtok($downloadResult->contentType, ';') ?: $downloadResult->contentType));

        if (! in_array($mediaType, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true)) {
            throw new RuntimeException(
                sprintf('Downloaded content is not a PDF: unexpected content type "%s".', $downloadResult->contentType),
                $downloadResult->statusCode,
            );
        }
    }

    private function assertValidXml(DownloadResult $downloadResult, int $maxBytes): void
    {
        if (strlen($downloadResult->content) > $maxBytes) {
            throw new RuntimeException(
                sprintf('Downloaded XML exceeds size limit of %d bytes.', $maxBytes),
                $downloadResult->statusCode,
            );
        }

        $trimmed = ltrim($downloadResult->content);

        if ($trimmed === '' || ! str_starts_with($trimmed, '<')) {
            throw new RuntimeException('Downloaded content is not XML: missing opening tag.', $downloadResult->statusCode);
        }

        if (preg_match('/<\s*html[\s>]/i', $trimmed) === 1) {
            throw new RuntimeException('Downloaded content is not XML full text: received an HTML page.', $downloadResult->statusCode);
        }

        $this->parseXml($downloadResult->content, $downloadResult->statusCode);

        if ($downloadResult->contentType === null || $downloadResult->contentType === '') {
            return;
        }

        $mediaType = strtolower(trim(strtok($downloadResult->contentType, ';') ?: $downloadResult->contentType));

        if (! in_array($mediaType, ['application/xml', 'text/xml', 'application/oai-pmh+xml'], true)
            && ! str_ends_with($mediaType, '+xml')) {
            throw new RuntimeException(
                sprintf('Downloaded content is not XML: unexpected content type "%s".', $downloadResult->contentType),
                $downloadResult->statusCode,
            );
        }
    }

    private function assertValidText(DownloadResult $downloadResult, int $maxBytes): void
    {
        if (strlen($downloadResult->content) > $maxBytes) {
            throw new RuntimeException(
                sprintf('Downloaded text exceeds size limit of %d bytes.', $maxBytes),
                $downloadResult->statusCode,
            );
        }

        if (trim($downloadResult->content) === '') {
            throw new RuntimeException('Downloaded text is empty.', $downloadResult->statusCode);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function metadataWithStoredArtifacts(
        array $metadata,
        FullTextSourceCandidate $candidate,
        RetrieveFullText $command,
        WorkId $workId,
        string $sourceAlias,
        string $content,
    ): array {
        if ($candidate->artifactType !== FullTextArtifactType::XML) {
            return $metadata;
        }

        $text = $this->extractTextFromXml($content);
        if ($text === null) {
            return $metadata;
        }

        $textPath = $this->storagePathFor($command, $workId, $sourceAlias, FullTextArtifactType::TEXT);
        $metadata['text_file_path'] = $this->storage->store($textPath, $text);
        $metadata['text_extraction'] = 'xml_text_content';

        return $metadata;
    }

    private function extensionFor(FullTextArtifactType $artifactType): string
    {
        return match ($artifactType) {
            FullTextArtifactType::PDF => 'pdf',
            FullTextArtifactType::XML => 'xml',
            FullTextArtifactType::TEXT => 'txt',
        };
    }

    private function extractTextFromXml(string $content): ?string
    {
        $xml = $this->parseXml($content);
        $dom = dom_import_simplexml($xml);
        $document = $dom?->ownerDocument;

        if ($document === null) {
            return null;
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//text()[normalize-space()]');

        if ($nodes === false) {
            return null;
        }

        $parts = [];
        foreach ($nodes as $node) {
            $part = preg_replace('/\s+/', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_XML1)) ?? '';
            $part = trim($part);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        $text = implode(' ', $parts);

        return $text === '' ? null : $text;
    }

    private function parseXml(string $content, int $statusCode = 0): \SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $xml instanceof \SimpleXMLElement) {
            throw new RuntimeException('Downloaded content is not valid XML.', $statusCode);
        }

        return $xml;
    }
}
