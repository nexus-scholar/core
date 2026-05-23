<?php

declare(strict_types=1);

namespace Nexus\Laravel\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Application\UseCase\RetrieveFullText;
use Nexus\Dissemination\Application\UseCase\RetrieveFullTextHandler;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Shared\Domain\ScholarlyWork;
use Throwable;

final class RetrieveFullTextJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ScholarlyWork $work,
        public string $destinationFolder = 'pdfs',
    ) {}

    public function handle(RetrieveFullTextHandler $handler, Dispatcher $events): FullTextResult
    {
        $context = $this->eventContext();
        $runId = $this->newRunId();
        $startedAt = hrtime(true);
        $events->dispatch(new NexusJobStarted($runId, 'retrieve_full_text', self::class, $context));

        try {
            $result = $handler->handle(new RetrieveFullText($this->work, $this->destinationFolder));
            $events->dispatch(new NexusJobCompleted(
                runId: $runId,
                jobName: 'retrieve_full_text',
                jobClass: self::class,
                context: $context,
                summary: [
                    'status' => $result->status->value,
                    'source_alias' => $result->sourceAlias,
                    'file_path' => $result->filePath,
                    'http_status' => $result->httpStatus,
                    'error_message' => $result->errorMessage,
                ],
                durationMs: $this->elapsedMs($startedAt),
            ));

            return $result;
        } catch (Throwable $error) {
            $events->dispatch(new NexusJobFailed(
                runId: $runId,
                jobName: 'retrieve_full_text',
                jobClass: self::class,
                context: $context,
                errorClass: $error::class,
                errorMessage: $error->getMessage(),
                durationMs: $this->elapsedMs($startedAt),
            ));

            throw $error;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventContext(): array
    {
        return [
            'work_id' => $this->work->primaryId()?->toString(),
            'destination_folder' => $this->destinationFolder,
        ];
    }

    private function elapsedMs(float|int $startNs): int
    {
        return (int) round((hrtime(true) - $startNs) / 1_000_000);
    }

    private function newRunId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
