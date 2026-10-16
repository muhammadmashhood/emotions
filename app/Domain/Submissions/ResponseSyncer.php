<?php

namespace App\Domain\Submissions;

use App\Domain\Typeform\TypeformClient;
use App\Enums\SubmissionSource;
use App\Models\Form;
use Illuminate\Support\Facades\Log;

/**
 * Backfill + reconcile through the Responses API (spec 5.8). Same answer shapes as webhooks.
 */
class ResponseSyncer
{
    private const MAX_PAGES = 200;

    public function __construct(private readonly SubmissionIngestor $ingestor) {}

    /**
     * Page through every completed response (newest first, `before` token pagination).
     *
     * @return array{fetched: int, created: int}
     */
    public function backfill(Form $form, ?TypeformClient $client = null): array
    {
        return $this->sync($form, ['completed' => 'true'], SubmissionSource::Backfill, $client);
    }

    /**
     * Fetch responses since (last_synced_at − 24 h) and ingest the missing ones.
     *
     * @return array{fetched: int, created: int}
     */
    public function reconcile(Form $form, ?TypeformClient $client = null): array
    {
        $since = ($form->last_synced_at ?? $form->created_at ?? now())->copy()->subDay();

        return $this->sync($form, ['completed' => 'true', 'since' => $since->toIso8601ZuluString()], SubmissionSource::Reconcile, $client);
    }

    /**
     * @param  array<string, scalar|null>  $baseQuery
     * @return array{fetched: int, created: int}
     */
    private function sync(Form $form, array $baseQuery, SubmissionSource $source, ?TypeformClient $client): array
    {
        $client ??= TypeformClient::for($form->connection);
        $form->loadMissing('hospital', 'currentVersion');

        $fetched = 0;
        $created = 0;
        $before = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $client->responses($form->typeform_form_id, $baseQuery + ['before' => $before]);
            if ($result->isEmpty()) {
                break;
            }

            foreach ($result->items as $item) {
                $fetched++;
                try {
                    $outcome = $this->ingestor->ingest($form, $item, $source);
                    if (! $outcome->isDuplicate()) {
                        $created++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('typeform.sync_item_failed', ['form_id' => $form->id, 'token' => $item['token'] ?? null, 'error' => $e->getMessage()]);
                }
            }

            $before = $result->lastToken();
            if ($before === null || count($result->items) < 1000) {
                break;
            }
        }

        $form->forceFill(['last_synced_at' => now()])->save();

        return ['fetched' => $fetched, 'created' => $created];
    }
}
