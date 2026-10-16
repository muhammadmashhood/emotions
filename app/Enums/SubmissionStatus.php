<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
