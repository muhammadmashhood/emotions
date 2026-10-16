<?php

namespace App\Domain\Typeform;

use App\Domain\Typeform\Data\NormalizedAnswer;

/**
 * Webhook / Responses API answer → normalised value (spec 7.1). Pure.
 */
class AnswerNormalizer
{
    /**
     * @param  list<mixed>  $answers  raw answer objects; malformed entries are skipped
     * @return list<NormalizedAnswer>
     */
    public function normalizeAll(array $answers): array
    {
        $out = [];
        foreach ($answers as $answer) {
            if (is_array($answer) && isset($answer['field']['id'])) {
                $out[] = $this->normalize($answer);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    public function normalize(array $answer): NormalizedAnswer
    {
        $type = (string) ($answer['type'] ?? 'unknown');
        $field = is_array($answer['field'] ?? null) ? $answer['field'] : [];
        $fieldId = (string) ($field['id'] ?? '');
        $fieldType = isset($field['type']) ? (string) $field['type'] : null;
        $fieldRef = isset($field['ref']) ? (string) $field['ref'] : null;

        [$text, $number, $json] = match ($type) {
            'text', 'email', 'url', 'phone_number' => [(string) ($answer[$type] ?? ''), null, null],
            'number' => [$this->numberToString($answer['number'] ?? null), is_numeric($answer['number'] ?? null) ? (float) $answer['number'] : null, null],
            'boolean' => [($answer['boolean'] ?? false) ? 'Yes' : 'No', ($answer['boolean'] ?? false) ? 1.0 : 0.0, null],
            'choice' => $this->choice(is_array($answer['choice'] ?? null) ? $answer['choice'] : []),
            'choices' => $this->choices(is_array($answer['choices'] ?? null) ? $answer['choices'] : []),
            'date' => [$this->date($answer['date'] ?? null), null, null],
            'file_url' => [basename((string) parse_url((string) ($answer['file_url'] ?? ''), PHP_URL_PATH)), null, ['url' => (string) ($answer['file_url'] ?? '')]],
            'payment' => $this->payment(is_array($answer['payment'] ?? null) ? $answer['payment'] : []),
            default => [json_encode($answer[$type] ?? null, JSON_UNESCAPED_UNICODE) ?: null, null, ['raw' => $answer[$type] ?? null]],
        };

        return new NormalizedAnswer($fieldId, $fieldType, $fieldRef, $type, $text, $number, $json, $answer);
    }

    private function numberToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $choice
     * @return array{string|null, null, array<string, mixed>}
     */
    private function choice(array $choice): array
    {
        $label = isset($choice['label']) ? (string) $choice['label'] : null;
        $other = isset($choice['other']) ? (string) $choice['other'] : null;

        return [
            $label !== null && $label !== '' ? $label : $other,
            null,
            ['id' => $choice['id'] ?? null, 'ref' => $choice['ref'] ?? null, 'label' => $label, 'other' => $other],
        ];
    }

    /**
     * @param  array<string, mixed>  $choices
     * @return array{string, null, array<string, mixed>}
     */
    private function choices(array $choices): array
    {
        $labels = array_values(array_map('strval', $choices['labels'] ?? []));
        $other = isset($choices['other']) ? (string) $choices['other'] : null;
        $parts = $labels;
        if ($other !== null && $other !== '') {
            $parts[] = $other;
        }

        return [
            implode(', ', $parts),
            null,
            ['ids' => $choices['ids'] ?? [], 'refs' => $choices['refs'] ?? [], 'labels' => $labels, 'other' => $other],
        ];
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? $value : date('Y-m-d', $ts);
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array{string, float|null, array<string, mixed>}
     */
    private function payment(array $payment): array
    {
        $amount = $payment['amount'] ?? null;
        $numeric = is_numeric($amount) ? (float) $amount : null;
        $formatted = trim(($numeric !== null ? number_format($numeric, 2) : (string) $amount).' '.(string) ($payment['currency'] ?? ''));

        return [$formatted, $numeric, $payment];
    }
}
