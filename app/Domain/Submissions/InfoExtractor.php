<?php

namespace App\Domain\Submissions;

use App\Domain\Scoring\ScoringMapping;
use App\Domain\Typeform\Data\NormalizedAnswer;
use App\Enums\InfoKey;

/**
 * Builds the `submissions.info` summary: mapped info fields first, hidden values fill the gaps. Pure.
 */
final class InfoExtractor
{
    /**
     * @param  list<NormalizedAnswer>  $answers
     * @param  array<string, mixed>  $hidden
     * @return array<string, string>
     */
    public static function extract(ScoringMapping $mapping, array $answers, array $hidden): array
    {
        $info = [];
        /** @var array<string, list<float>> $numeric values of keys that may be carried by several questions */
        $numeric = [];
        foreach ($answers as $answer) {
            $field = $mapping->field($answer->fieldId);
            if ($field?->infoKey === null || $answer->valueText === null || $answer->valueText === '') {
                continue;
            }
            if (InfoKey::from($field->infoKey)->allowsMultiple() && $answer->valueNumber !== null) {
                $numeric[$field->infoKey][] = $answer->valueNumber;

                continue;
            }
            $info[$field->infoKey] = $answer->valueText;
        }
        foreach ($numeric as $key => $values) {
            $avg = array_sum($values) / count($values);
            $info[$key] = (string) (floor($avg) === $avg ? (int) $avg : round($avg, 1));
        }
        foreach (InfoKey::values() as $key) {
            if (! isset($info[$key]) && isset($hidden[$key]) && is_scalar($hidden[$key]) && (string) $hidden[$key] !== '') {
                $info[$key] = (string) $hidden[$key];
            }
        }

        return $info;
    }
}
