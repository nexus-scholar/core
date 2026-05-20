<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use DateTimeImmutable;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
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

        $lastFailure = null;

        foreach ($this->sources->all() as $source) {
            if (! $source->supports($command->work)) {
                continue;
            }

            $startTime = hrtime(true);
            $url = null;

            try {
                $url = $source->resolve($command->work);
                if ($url === null) {
                    continue;
                }

                if ($this->isInFailedAttemptCooldown($workId, $url, $command)) {
                    continue;
                }

                $downloadResult = $this->downloadWithRetry($url, $command);
                $this->assertValidPdf($downloadResult, $command->maxBytes);
                $content = $downloadResult->content;
                
                $extension = 'pdf'; // Assume PDF for now
                $filename = sprintf(
                    '%s_%s.%s',
                    $workId->toString(),
                    $source->alias(),
                    $extension
                );

                $fullPath = $command->destinationFolder . '/' . $filename;
                $storedPath = $this->storage->store($fullPath, $content);

                $result = FullTextResult::success($storedPath, $source->alias(), $downloadResult->statusCode);
                $this->repository->save($workId, $url, $result, $this->elapsedMs($startTime));

                return $result;
            } catch (Throwable $e) {
                $status = method_exists($e, 'getCode') ? $e->getCode() : null;
                $result = FullTextResult::failure($e->getMessage(), $source->alias(), (int) $status);
                $this->repository->save($workId, $url ?? 'unknown', $result, $this->elapsedMs($startTime));
                $lastFailure = $result;
                // Continue to next source
            }
        }

        return $lastFailure ?? FullTextResult::failure("No PDF found across all sources");
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
