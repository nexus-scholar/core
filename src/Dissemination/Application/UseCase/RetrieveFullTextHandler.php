<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
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

                $downloadResult = $this->downloader->download($url);
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
                // Continue to next source
            }
        }

        return FullTextResult::failure("No PDF found across all sources");
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }
}
