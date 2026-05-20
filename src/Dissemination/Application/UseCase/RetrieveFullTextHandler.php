<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use DateTimeImmutable;
use Nexus\Dissemination\Application\Dto\FullTextResult;
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

                if (! $candidate->isPdf()) {
                    $result = FullTextResult::skipped(
                        sprintf(
                            'Source resolved a %s full-text artifact; storage for non-PDF artifacts is not implemented yet.',
                            $candidate->artifactType->value,
                        ),
                        $source->alias(),
                        $metadata,
                    );
                    $this->repository->save($workId, $url, $result, $this->elapsedMs($startTime));
                    $lastResult = $result;

                    continue;
                }

                if ($this->isInFailedAttemptCooldown($workId, $url, $command)) {
                    continue;
                }

                $downloadResult = $this->downloadWithRetry($url, $command);
                $this->assertValidPdf($downloadResult, $command->maxBytes);
                $content = $downloadResult->content;
                
                $fullPath = $this->storagePathFor($command, $workId, $source->alias());
                $storedPath = $this->storage->store($fullPath, $content);

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

        return $lastResult ?? FullTextResult::failure("No PDF found across all sources");
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

        throw $lastError ?? new RuntimeException('Failed to download PDF.');
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }

    private function storagePathFor(RetrieveFullText $command, WorkId $workId, string $sourceAlias): string
    {
        $folder = $this->safeFolder($command->destinationFolder);
        $workSegment = $this->safePathSegment($workId->toString(), 80);
        $sourceSegment = $this->safePathSegment($sourceAlias, 40);
        $hash = substr(hash('sha256', $workId->toString() . '|' . $sourceAlias), 0, 16);
        $filename = sprintf('%s_%s_%s.pdf', $workSegment, $sourceSegment, $hash);

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
}
