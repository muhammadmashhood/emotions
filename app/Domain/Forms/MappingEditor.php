<?php

namespace App\Domain\Forms;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\FormStructureParser;
use App\Enums\InfoKey;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Persists mapping changes made in the editor (spec 6.6). Input is a plain array description of the desired state.
 *
 * Sections: list of ['id' => int|null, 'name' => string, 'weight' => float, 'fields' => list<int field id>]
 */
class MappingEditor
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{id: int|null, name: string, weight: float|int|string, fields: list<int|string>}>  $sections
     * @param  array<int|string, array{is_scored: bool, info_key: string|null}>  $fields  keyed by field id
     * @return array<string, mixed> diff written to the audit log
     */
    public function save(FormVersion $version, array $sections, array $fields, User $by): array
    {
        $existingFields = $version->fields()->with('section')->get()->keyBy('id');
        $existingSections = $version->sections()->get()->keyBy('id');

        $this->validate($sections, $fields, $existingFields->keys()->all(), $existingSections->keys()->all());

        return DB::transaction(function () use ($version, $sections, $fields, $existingFields, $existingSections, $by): array {
            $diff = ['sections' => [], 'fields' => []];
            $keptSectionIds = [];
            $order = 0;
            $fieldOrder = 0;

            foreach ($sections as $input) {
                $name = trim((string) $input['name']);
                $weight = round((float) $input['weight'], 2);

                if ($input['id'] !== null && $existingSections->has((int) $input['id'])) {
                    /** @var Section $section */
                    $section = $existingSections->get((int) $input['id']);
                    $changes = [];
                    if ($section->name !== $name) {
                        $changes['name'] = [$section->name, $name];
                    }
                    if ((float) $section->weight !== $weight) {
                        $changes['weight'] = [(float) $section->weight, $weight];
                    }
                    if ($section->display_order !== $order) {
                        $changes['order'] = [$section->display_order, $order];
                    }
                    $section->fill(['name' => $name, 'weight' => $weight, 'display_order' => $order])->save();
                    if ($changes !== []) {
                        $diff['sections'][$section->id] = $changes;
                    }
                } else {
                    $section = Section::query()->create([
                        'form_version_id' => $version->id,
                        'name' => $name,
                        'source_ref' => FormVersioner::ADMIN_SECTION_PREFIX.Str::lower(Str::random(8)),
                        'display_order' => $order,
                        'weight' => $weight,
                    ]);
                    $diff['sections'][$section->id] = ['created' => $name];
                }
                $keptSectionIds[] = $section->id;
                $order++;

                foreach ($input['fields'] as $fieldId) {
                    /** @var FormField $field */
                    $field = $existingFields->get((int) $fieldId);
                    $state = $fields[$fieldId] ?? $fields[(string) $fieldId] ?? ['is_scored' => $field->is_scored, 'info_key' => $field->info_key];
                    $isScored = (bool) $state['is_scored'] && self::isScorable($field);
                    $infoKey = $state['info_key'] !== null && $state['info_key'] !== '' ? (string) $state['info_key'] : null;

                    $changes = [];
                    if ($field->section_id !== $section->id) {
                        $changes['section'] = [$field->section->name, $section->name];
                    }
                    if ($field->is_scored !== $isScored) {
                        $changes['is_scored'] = [$field->is_scored, $isScored];
                    }
                    if ($field->info_key !== $infoKey) {
                        $changes['info_key'] = [$field->info_key, $infoKey];
                    }

                    $field->fill([
                        'section_id' => $section->id,
                        'is_scored' => $isScored,
                        'info_key' => $infoKey,
                        'display_order' => $fieldOrder++,
                        'is_new_in_version' => false,
                    ])->save();

                    if ($changes !== []) {
                        $diff['fields'][$field->id] = ['title' => $field->title] + $changes;
                    }
                }
            }

            foreach ($existingSections as $section) {
                if (! in_array($section->id, $keptSectionIds, true)) {
                    $diff['sections'][$section->id] = ['deleted' => $section->name];
                    $section->delete();
                }
            }

            $version->forceFill(['needs_review' => false, 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();

            $this->audit->log('mapping.updated', $version, $diff, $version->form->hospital_id, $by);

            return $diff;
        });
    }

    public static function isScorable(FormField $field): bool
    {
        if (in_array($field->type, ['opinion_scale', 'rating', 'nps', 'yes_no'], true)) {
            return true;
        }

        return in_array($field->type, ['dropdown', 'multiple_choice'], true)
            && is_array($field->choices)
            && FormStructureParser::isYesNoChoiceList($field->choices);
    }

    /**
     * @param  list<array{id: int|null, name: string, weight: float|int|string, fields: list<int|string>}>  $sections
     * @param  array<int|string, array{is_scored: bool, info_key: string|null}>  $fields
     * @param  list<int>  $allFieldIds
     * @param  list<int>  $existingSectionIds
     */
    private function validate(array $sections, array $fields, array $allFieldIds, array $existingSectionIds): void
    {
        $errors = [];
        $placed = [];

        foreach ($sections as $i => $section) {
            if (trim((string) $section['name']) === '') {
                $errors["sections.$i.name"] = 'Every section needs a name.';
            }
            $w = (float) $section['weight'];
            if ($w < 0 || $w > 10) {
                $errors["sections.$i.weight"] = 'Weight must be between 0 and 10.';
            }
            if ($section['id'] !== null && ! in_array((int) $section['id'], $existingSectionIds, true)) {
                $errors["sections.$i"] = 'Unknown section.';
            }
            foreach ($section['fields'] as $fid) {
                $placed[] = (int) $fid;
            }
        }

        sort($placed);
        $expected = $allFieldIds;
        sort($expected);
        if ($placed !== $expected) {
            $errors['fields'] = 'Every question must be in exactly one section.';
        }

        $seenKeys = [];
        foreach ($fields as $fid => $state) {
            $key = $state['info_key'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            if (! in_array($key, InfoKey::values(), true)) {
                $errors["fields.$fid.info_key"] = 'Unknown info key.';

                continue;
            }
            if (isset($seenKeys[$key])) {
                $errors["fields.$fid.info_key"] = 'Only one question can provide "'.InfoKey::from($key)->label().'".';
            }
            $seenKeys[$key] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
