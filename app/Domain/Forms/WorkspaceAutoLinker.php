<?php

namespace App\Domain\Forms;

use App\Domain\Typeform\ConnectionManager;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\TypeformClient;
use App\Models\Form;
use App\Models\Hospital;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Milestone 7: hospitals mapped to a Typeform workspace get new forms in that workspace linked automatically.
 * New links start with `needs_review = true`, so the admin still checks the mapping.
 */
class WorkspaceAutoLinker
{
    public function __construct(
        private readonly FormLinker $linker,
        private readonly ConnectionManager $connections,
    ) {}

    /**
     * @return array<string, int> hospital slug → number of forms linked
     */
    public function run(): array
    {
        $connection = $this->connections->current();
        if ($connection === null) {
            return [];
        }

        $client = TypeformClient::for($connection);
        /** @var array<string, bool> $linkedIds */
        $linkedIds = array_fill_keys(Form::withTrashed()->pluck('typeform_form_id')->all(), true);
        $result = [];

        foreach (Hospital::query()->where('is_active', true)->whereNotNull('typeform_workspace_id')->get() as $hospital) {
            $count = 0;
            try {
                $page = $client->forms(1, 200, null, $hospital->typeform_workspace_id);
            } catch (TypeformException $e) {
                Log::warning('typeform.autolink_failed', ['hospital_id' => $hospital->id, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($page->items as $summary) {
                if (isset($linkedIds[$summary->id])) {
                    continue;
                }
                try {
                    $this->linker->link($hospital, $connection, $summary->id, null);
                    $linkedIds[$summary->id] = true;
                    $count++;
                } catch (ValidationException|TypeformException $e) {
                    Log::warning('typeform.autolink_form_failed', ['hospital_id' => $hospital->id, 'typeform_form_id' => $summary->id, 'error' => $e->getMessage()]);
                }
            }
            $result[$hospital->slug] = $count;
        }

        return $result;
    }
}
