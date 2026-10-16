<?php

namespace App\Domain\Scoring;

use App\Models\FormVersion;

/**
 * Plain, framework-free description of a version's mapping. Built once per scoring run.
 */
final readonly class ScoringMapping
{
    /**
     * @param  list<MappedSection>  $sections  in display order
     * @param  array<string, MappedField>  $fields  keyed by Typeform field id
     */
    public function __construct(public array $sections, public array $fields) {}

    public static function fromVersion(FormVersion $version): self
    {
        $sections = $version->sections()->get()->map(fn ($s) => new MappedSection($s->id, $s->name, $s->display_order, (float) $s->weight))->values()->all();
        $fields = [];
        foreach ($version->fields()->get() as $f) {
            $fields[$f->typeform_field_id] = new MappedField($f->id, $f->typeform_field_id, $f->type, $f->section_id, $f->is_scored, $f->scale_min, $f->scale_max, $f->info_key, $f->display_order, $f->title);
        }

        return new self($sections, $fields);
    }

    public function field(string $typeformFieldId): ?MappedField
    {
        return $this->fields[$typeformFieldId] ?? null;
    }

    /** @return list<string> */
    public function fieldIds(): array
    {
        return array_keys($this->fields);
    }
}
