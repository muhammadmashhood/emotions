<?php

namespace App\Domain\Submissions;

use App\Domain\Scoring\ScoringMapping;
use App\Domain\Typeform\Data\NormalizedAnswer;
use App\Models\Branch;
use App\Models\Form;

/**
 * Resolves a submission's branch from `forms.settings.branch_source` (spec 4.5.1, milestone 7).
 * Branches are created on first sight so the admin can rename / deactivate them later.
 */
class BranchResolver
{
    /**
     * @param  list<NormalizedAnswer>  $answers
     * @param  array<string, mixed>  $hidden
     */
    public function resolve(Form $form, ScoringMapping $mapping, array $answers, array $hidden): ?int
    {
        $source = $form->settings->branchSource;
        if ($source === null) {
            return null;
        }

        $name = null;
        if ($source['type'] === 'field' && isset($source['field_id'])) {
            foreach ($answers as $answer) {
                if ($answer->fieldId === (string) $source['field_id']) {
                    $name = $answer->valueText;
                    break;
                }
            }
        } elseif ($source['type'] === 'hidden') {
            $key = (string) ($source['key'] ?? 'branch');
            $value = $hidden[$key] ?? null;
            $name = is_scalar($value) ? (string) $value : null;
        }

        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Branch::query()->firstOrCreate(
            ['hospital_id' => $form->hospital_id, 'name' => mb_substr($name, 0, 120)],
            ['is_active' => true],
        )->id;
    }
}
