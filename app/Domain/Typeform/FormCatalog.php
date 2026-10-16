<?php

namespace App\Domain\Typeform;

use App\Domain\Typeform\Data\FormSummary;
use App\Domain\Typeform\Data\Workspace;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Lists Typeform forms available for linking. Results are cached briefly so typing in the
 * search box does not hammer the API (Typeform allows ~2 requests/second).
 */
class FormCatalog
{
    private const CACHE_SECONDS = 60;

    /** @return list<Workspace> */
    public function workspaces(TypeformConnection $connection): array
    {
        return Cache::remember("typeform:{$connection->id}:workspaces", self::CACHE_SECONDS * 5, fn () => TypeformClient::for($connection)->workspaces());
    }

    /**
     * Forms not yet linked to any hospital (forms unlinked from this hospital may be re-linked).
     *
     * @return Collection<int, FormSummary>
     */
    public function availableForms(TypeformConnection $connection, Hospital $hospital, string $search = '', ?string $workspaceId = null): Collection
    {
        $key = "typeform:{$connection->id}:forms:".md5($search.'|'.($workspaceId ?? '*'));

        /** @var list<FormSummary> $forms */
        $forms = Cache::remember($key, self::CACHE_SECONDS, function () use ($connection, $search, $workspaceId) {
            $client = TypeformClient::for($connection);
            $workspaces = collect($this->workspaces($connection))->keyBy('id');

            if ($workspaceId !== null && $workspaceId !== '') {
                return $client->forms(1, 200, $search ?: null, $workspaceId, $workspaces->get($workspaceId)?->name)->items;
            }

            if ($workspaces->isEmpty()) {
                return $client->forms(1, 200, $search ?: null)->items;
            }

            $all = [];
            foreach ($workspaces as $workspace) {
                $page = $client->forms(1, 200, $search ?: null, $workspace->id, $workspace->name);
                array_push($all, ...$page->items);
            }

            return $all;
        });

        $takenElsewhere = Form::query()
            ->where(fn ($q) => $q->where('status', FormStatus::Active->value)->orWhere('hospital_id', '!=', $hospital->id))
            ->pluck('typeform_form_id')
            ->all();

        return collect($forms)
            ->reject(fn (FormSummary $f) => in_array($f->id, $takenElsewhere, true))
            ->sortByDesc(fn (FormSummary $f) => $f->lastUpdatedAt ?? '')
            ->values();
    }

    public function forget(TypeformConnection $connection): void
    {
        Cache::forget("typeform:{$connection->id}:workspaces");
    }
}
