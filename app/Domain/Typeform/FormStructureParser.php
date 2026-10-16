<?php

namespace App\Domain\Typeform;

use App\Domain\Typeform\Data\ParsedField;
use App\Domain\Typeform\Data\ParsedSection;
use App\Domain\Typeform\Data\ParsedStructure;

/**
 * Pure: form JSON → sections + fields with defaults (spec section 6.1 / 6.2). No I/O.
 */
class FormStructureParser
{
    private const SCALE_TYPES = ['opinion_scale', 'rating', 'nps'];

    private const YES_NO_LABELS = ['yes', 'no', 'na', 'n/a', 'not applicable'];

    /** @var array<string, list<string>> */
    private const INFO_KEY_PATTERNS = [
        'facility' => ['healthcare facility', 'hospital name', 'facility'],
        'location' => ['location', 'city'],
        'visit_date' => ['visit date', 'vsite date', 'visite date', 'date of visit'],
        'doctor' => ['name of the doctor', 'doctor name'],
        'booking_method' => ['booking method'],
    ];

    private int $order = 0;

    /** @var list<ParsedSection> */
    private array $sections = [];

    private ParsedSection $current;

    /**
     * @param  array<string, mixed>  $formJson  full `GET /forms/{id}` payload
     */
    public function parse(array $formJson): ParsedStructure
    {
        $this->order = 0;
        $this->sections = [];
        $this->current = new ParsedSection('General', null);
        $this->sections[] = $this->current;

        $this->walk($formJson['fields'] ?? [], null);

        $sections = array_values(array_filter($this->sections, fn (ParsedSection $s) => ! ($s->sourceRef === null && $s->name === 'General' && $s->isEmpty())));

        return new ParsedStructure($this->assignInfoKeys($sections));
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function walk(array $fields, ?ParsedSection $groupSection): void
    {
        foreach ($fields as $field) {
            $type = (string) ($field['type'] ?? '');

            if ($type === 'statement') {
                $section = new ParsedSection(self::cleanTitle((string) ($field['title'] ?? '')), $field['ref'] ?? null);
                $this->sections[] = $section;
                $this->current = $section;

                continue;
            }

            if ($type === 'group') {
                if ($groupSection === null) {
                    $section = new ParsedSection(self::cleanTitle((string) ($field['title'] ?? '')), $field['ref'] ?? null);
                    $this->sections[] = $section;
                } else {
                    // nested group: children belong to the enclosing group's section
                    $section = $groupSection;
                }
                $this->walk($field['properties']['fields'] ?? [], $section);

                continue;
            }

            $target = $groupSection ?? $this->current;
            $target->add($this->parseField($field));
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function parseField(array $field): ParsedField
    {
        $type = (string) ($field['type'] ?? 'unknown');
        $props = is_array($field['properties'] ?? null) ? $field['properties'] : [];
        [$min, $max] = $this->scaleFor($type, $props);
        $choices = $this->choicesFor($props);

        return new ParsedField(
            id: (string) $field['id'],
            ref: isset($field['ref']) ? (string) $field['ref'] : null,
            title: trim((string) ($field['title'] ?? '')),
            description: isset($props['description']) ? (string) $props['description'] : (isset($field['description']) ? (string) $field['description'] : null),
            type: $type,
            isScored: $this->defaultScored($type, $choices),
            scaleMin: $min,
            scaleMax: $max,
            choices: $choices,
            infoKey: null,
            displayOrder: $this->order++,
        );
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{int|null, int|null}
     */
    private function scaleFor(string $type, array $props): array
    {
        return match ($type) {
            'opinion_scale' => (function () use ($props): array {
                $steps = (int) ($props['steps'] ?? 11);
                $min = ! empty($props['start_at_one']) ? 1 : 0;

                return [$min, $min + $steps - 1];
            })(),
            'rating' => [1, (int) ($props['steps'] ?? 5)],
            'nps' => [0, 10],
            default => [null, null],
        };
    }

    /**
     * @param  array<string, mixed>  $props
     * @return list<array{id: string, ref: string|null, label: string}>|null
     */
    private function choicesFor(array $props): ?array
    {
        if (! isset($props['choices']) || ! is_array($props['choices'])) {
            return null;
        }

        return array_values(array_map(fn (array $c) => [
            'id' => (string) ($c['id'] ?? ''),
            'ref' => isset($c['ref']) ? (string) $c['ref'] : null,
            'label' => (string) ($c['label'] ?? ''),
        ], $props['choices']));
    }

    /**
     * @param  list<array{id: string, ref: string|null, label: string}>|null  $choices
     */
    private function defaultScored(string $type, ?array $choices): bool
    {
        if (in_array($type, self::SCALE_TYPES, true) || $type === 'yes_no') {
            return true;
        }

        if (in_array($type, ['dropdown', 'multiple_choice'], true) && $choices !== null) {
            return self::isYesNoChoiceList($choices);
        }

        return false;
    }

    /**
     * @param  list<array{id: string, ref: string|null, label: string}>  $choices
     */
    public static function isYesNoChoiceList(array $choices): bool
    {
        $labels = array_map(fn (array $c) => mb_strtolower(trim($c['label'])), $choices);
        if ($labels === []) {
            return false;
        }

        $onlyAllowed = array_diff($labels, self::YES_NO_LABELS) === [];

        return $onlyAllowed && in_array('yes', $labels, true) && in_array('no', $labels, true);
    }

    public static function isNotApplicableLabel(string $label): bool
    {
        return in_array(mb_strtolower(trim($label)), ['na', 'n/a', 'not applicable'], true);
    }

    /**
     * @param  list<ParsedSection>  $sections
     * @return list<ParsedSection>
     */
    private function assignInfoKeys(array $sections): array
    {
        $taken = [];
        foreach ($sections as $section) {
            foreach ($section->fields as $i => $field) {
                $key = $this->detectInfoKey($field->title, $taken);
                if ($key !== null) {
                    $taken[] = $key;
                    $section->fields[$i] = $field->withInfoKey($key);
                }
            }
        }

        return $sections;
    }

    /**
     * @param  list<string>  $taken
     */
    private function detectInfoKey(string $title, array $taken): ?string
    {
        $haystack = mb_strtolower($title);
        foreach (self::INFO_KEY_PATTERNS as $key => $needles) {
            if (in_array($key, $taken, true)) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Strip markdown emphasis, trim, remove a leading "Section N —" prefix. Falls back to the original when empty.
     */
    public static function cleanTitle(string $title): string
    {
        $stripped = trim(str_replace(['*', '_'], '', $title));
        $cleaned = trim((string) preg_replace('/^\s*section\s*\d+\s*[—–\-:.]\s*/iu', '', $stripped));

        return $cleaned === '' ? trim($title) : $cleaned;
    }

    /**
     * sha256 of [id, type, ref, title, steps, start_at_one, choice ids] for every field, recursively (spec 6.5).
     *
     * @param  array<string, mixed>  $formJson
     */
    public static function hash(array $formJson): string
    {
        $rows = [];
        self::collectHashRows($formJson['fields'] ?? [], $rows);

        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<int, mixed>>  $rows
     */
    private static function collectHashRows(array $fields, array &$rows): void
    {
        foreach ($fields as $field) {
            $props = is_array($field['properties'] ?? null) ? $field['properties'] : [];
            $rows[] = [
                $field['id'] ?? null,
                $field['type'] ?? null,
                $field['ref'] ?? null,
                $field['title'] ?? null,
                $props['steps'] ?? null,
                $props['start_at_one'] ?? null,
                isset($props['choices']) && is_array($props['choices']) ? array_values(array_map(fn ($c) => $c['id'] ?? null, $props['choices'])) : null,
            ];
            if (isset($props['fields']) && is_array($props['fields'])) {
                self::collectHashRows($props['fields'], $rows);
            }
        }
    }
}
