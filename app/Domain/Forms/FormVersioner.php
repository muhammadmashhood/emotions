<?php

namespace App\Domain\Forms;

use App\Domain\Audit\AuditLogger;
use App\Domain\Typeform\Data\ParsedField;
use App\Domain\Typeform\Data\ParsedSection;
use App\Domain\Typeform\Data\ParsedStructure;
use App\Domain\Typeform\FormStructureParser;
use App\Domain\Typeform\TypeformClient;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

/**
 * Creates form versions from Typeform definitions and carries the admin's mapping over (spec section 6.3 – 6.5).
 */
class FormVersioner
{
    public const ADMIN_SECTION_PREFIX = 'admin-';

    public function __construct(
        private readonly FormStructureParser $parser,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $formJson
     */
    public function createInitialVersion(Form $form, array $formJson): FormVersion
    {
        return DB::transaction(function () use ($form, $formJson): FormVersion {
            $version = $this->createVersionFrom($form, $formJson, null);
            $version->forceFill(['needs_review' => true])->save();
            $form->forceFill(['current_version_id' => $version->id, 'title' => (string) ($formJson['title'] ?? $form->title)])->save();

            return $version;
        });
    }

    /**
     * Fetch the live definition; if the structure hash changed, create a new version with carry-over.
     * Returns the new version, or null when nothing changed. Always refreshes the title.
     */
    public function refreshFromTypeform(Form $form, ?TypeformClient $client = null): ?FormVersion
    {
        $client ??= TypeformClient::for($form->connection);
        $definition = $client->form($form->typeform_form_id);

        $title = (string) ($definition['title'] ?? $form->title);
        if ($title !== $form->title) {
            $form->forceFill(['title' => $title])->save();
        }

        $current = $form->currentVersion;
        if ($current !== null && $current->definition_hash === FormStructureParser::hash($definition)) {
            return null;
        }

        return DB::transaction(function () use ($form, $definition, $current): FormVersion {
            $version = $this->createVersionFrom($form, $definition, $current);
            $form->forceFill(['current_version_id' => $version->id])->save();
            $form->setRelation('currentVersion', $version);

            $this->audit->log('form.version_created', $form, [
                'version' => $version->version_number,
                'needs_review' => $version->needs_review,
                'new_fields' => $version->fields()->where('is_new_in_version', true)->count(),
            ], $form->hospital_id);

            return $version;
        });
    }

    /**
     * Build version N+1 from a definition, carrying over names, weights, scored flags, info keys and admin moves.
     *
     * @param  array<string, mixed>  $formJson
     */
    public function createVersionFrom(Form $form, array $formJson, ?FormVersion $previous): FormVersion
    {
        $parsed = $this->parser->parse($formJson);
        $prevSections = $previous?->sections()->get() ?? collect();
        $prevFields = $previous?->fields()->with('section')->get()->keyBy('typeform_field_id') ?? collect();

        // Where would the previous definition have put each field automatically? Needed to detect admin moves.
        $prevAutoSectionRef = [];
        if ($previous !== null) {
            foreach ($this->parser->parse($previous->definition)->sections as $section) {
                foreach ($section->fields as $field) {
                    $prevAutoSectionRef[$field->id] = $section->sourceRef;
                }
            }
        }

        $version = FormVersion::query()->create([
            'form_id' => $form->id,
            'version_number' => ($previous->version_number ?? ($form->versions()->max('version_number') ?? 0)) + 1,
            'definition' => $formJson,
            'definition_hash' => FormStructureParser::hash($formJson),
            'needs_review' => false,
        ]);

        /** @var array<string, Section> $sectionsByRef keyed by source_ref ('' for null) */
        $sectionsByRef = [];
        $order = 0;
        $anyNew = false;

        $prevByRef = $prevSections->keyBy(fn (Section $s) => $s->source_ref ?? '');

        foreach ($parsed->sections as $parsedSection) {
            $prev = $prevByRef->get($parsedSection->sourceRef ?? '');
            $sectionsByRef[$parsedSection->sourceRef ?? ''] = Section::query()->create([
                'form_version_id' => $version->id,
                'name' => $prev->name ?? $parsedSection->name,
                'source_ref' => $parsedSection->sourceRef,
                'display_order' => $order++,
                'weight' => $prev->weight ?? 1.0,
            ]);
        }

        // Admin-created sections survive version changes.
        foreach ($prevSections as $prevSection) {
            if ($prevSection->source_ref !== null && str_starts_with($prevSection->source_ref, self::ADMIN_SECTION_PREFIX)) {
                $sectionsByRef[$prevSection->source_ref] = Section::query()->create([
                    'form_version_id' => $version->id,
                    'name' => $prevSection->name,
                    'source_ref' => $prevSection->source_ref,
                    'display_order' => $order++,
                    'weight' => $prevSection->weight,
                ]);
            }
        }

        foreach ($parsed->sections as $parsedSection) {
            foreach ($parsedSection->fields as $parsedField) {
                /** @var FormField|null $prevField */
                $prevField = $prevFields->get($parsedField->id);
                $section = $sectionsByRef[$parsedSection->sourceRef ?? ''];

                if ($prevField !== null) {
                    $prevRef = $prevField->section->source_ref ?? '';
                    $adminMoved = ($prevAutoSectionRef[$parsedField->id] ?? '') !== $prevRef;
                    if ($adminMoved && isset($sectionsByRef[$prevRef])) {
                        $section = $sectionsByRef[$prevRef];
                    }
                } else {
                    $anyNew = $previous !== null;
                }

                FormField::query()->create([
                    'form_version_id' => $version->id,
                    'typeform_field_id' => $parsedField->id,
                    'typeform_ref' => $parsedField->ref,
                    'title' => $parsedField->title,
                    'description' => $parsedField->description,
                    'type' => $parsedField->type,
                    'section_id' => $section->id,
                    'display_order' => $parsedField->displayOrder,
                    'is_scored' => $prevField->is_scored ?? $parsedField->isScored,
                    'scale_min' => $parsedField->scaleMin,
                    'scale_max' => $parsedField->scaleMax,
                    'choices' => $parsedField->choices,
                    'info_key' => $prevField !== null ? $prevField->info_key : $parsedField->infoKey,
                    'is_new_in_version' => $prevField === null && $previous !== null,
                ]);
            }
        }

        $this->removeEmptyGeneral($version, $parsed);

        if ($anyNew) {
            $version->forceFill(['needs_review' => true])->save();
        }

        return $version->refresh();
    }

    /**
     * If carry-over emptied the automatic "General" section (no source_ref), drop it.
     */
    private function removeEmptyGeneral(FormVersion $version, ParsedStructure $parsed): void
    {
        $general = $version->sections()->whereNull('source_ref')->first();
        if ($general !== null && ! $general->fields()->exists()) {
            $general->delete();
        }
    }

    /**
     * Convenience for tests and tooling: parsed representation of a stored version.
     *
     * @return list<ParsedSection>
     */
    public function parsedSections(FormVersion $version): array
    {
        return $this->parser->parse($version->definition)->sections;
    }

    /** @return list<ParsedField> */
    public function parsedFields(FormVersion $version): array
    {
        return $this->parser->parse($version->definition)->allFields();
    }
}
