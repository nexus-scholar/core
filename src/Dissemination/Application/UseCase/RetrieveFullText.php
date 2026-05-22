<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use InvalidArgumentException;
use Nexus\Search\Domain\ScholarlyWork;

final readonly class RetrieveFullText
{
    public ScholarlyWork $work;

    public ?string $projectId;

    public string $destinationFolder;

    public int $maxDownloadAttempts;

    public int $maxBytes;

    public int $failedAttemptCooldownSeconds;

    public function __construct(
        ScholarlyWork $work,
        string $destinationFolder = 'pdfs',
        int $maxDownloadAttempts = 2,
        int $maxBytes = 50_000_000,
        int $failedAttemptCooldownSeconds = 3600,
        ?string $projectId = null,
    ) {
        if ($maxDownloadAttempts < 1) {
            throw new InvalidArgumentException('Full-text retrieval must allow at least one download attempt.');
        }

        if ($maxBytes < 1) {
            throw new InvalidArgumentException('Full-text retrieval maxBytes must be greater than zero.');
        }

        if ($failedAttemptCooldownSeconds < 0) {
            throw new InvalidArgumentException('Full-text retrieval failedAttemptCooldownSeconds must not be negative.');
        }

        $this->work = $work;
        $this->projectId = $projectId;
        $this->destinationFolder = $destinationFolder;
        $this->maxDownloadAttempts = $maxDownloadAttempts;
        $this->maxBytes = $maxBytes;
        $this->failedAttemptCooldownSeconds = $failedAttemptCooldownSeconds;
    }
}
