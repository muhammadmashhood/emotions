<?php

namespace App\Domain\Submissions;

use App\Models\Submission;

final readonly class IngestOutcome
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DUPLICATE = 'duplicate';

    public function __construct(public string $status, public ?Submission $submission) {}

    public function isDuplicate(): bool
    {
        return $this->status === self::DUPLICATE;
    }
}
