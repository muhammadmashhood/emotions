<?php

namespace App\Console\Commands;

use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class PruneWebhookDeliveries extends Command
{
    protected $signature = 'deliveries:prune {--days=}';

    protected $description = 'Delete webhook deliveries older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('emotion.webhook_delivery_retention_days', 90));
        $deleted = WebhookDelivery::query()->where('received_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
