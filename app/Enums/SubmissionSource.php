<?php

namespace App\Enums;

enum SubmissionSource: string
{
    case Webhook = 'webhook';
    case Backfill = 'backfill';
    case Reconcile = 'reconcile';
}
