<?php

namespace App\Livewire\Admin\Forms;

use App\Domain\Forms\MappingEditor;
use App\Enums\InfoKey;
use App\Jobs\RescoreFormSubmissions;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mapping editor (spec 6.6). Edits the current version; older versions are read-only.
 */
#[Layout('layouts.app')]
class Mapping extends Component
{
    public Form $form;

    public FormVersion $version;

    #[Url(as: 'version')]
    public ?int $versionNumber = null;

    /** @var list<array{key: string, id: int|null, name: string, weight: float|string, fields: list<int>}> */
    public array $sections = [];

    /** @var array<int, array{is_scored: bool, info_key: string|null}> */
    public array $fieldState = [];

    public bool $readOnly = false;

    public ?int $rescoreCount = null;

    private int $newSectionCounter = 0;

    public function mount(Form $form): void
    {
        $this->authorize('manage', $form);
        $this->form = $form;

        $version = $this->versionNumber !== null
            ? $form->versions()->where('version_number', $this->versionNumber)->firstOrFail()
            : $form->currentVersion;

        abort_unless($version instanceof FormVersion, 404);

        $this->version = $version;
        $this->readOnly = $version->id !== $form->current_version_id;
        $this->loadState();
    }

    private function loadState(): void
    {
        $this->sections = [];
        $this->fieldState = [];

        $fieldsBySection = $this->version->fields()->get()->groupBy('section_id');

        foreach ($this->version->sections()->get() as $section) {
            /** @var Collection<int, FormField> $fields */
            $fields = $fieldsBySection->get($section->id, collect())->sortBy('display_order');
            $this->sections[] = [
                'key' => 's'.$section->id,
                'id' => $section->id,
                'name' => $section->name,
                'weight' => (float) $section->weight,
                'fields' => $fields->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
            foreach ($fields as $field) {
                $this->fieldState[$field->id] = ['is_scored' => $field->is_scored, 'info_key' => $field->info_key];
            }
        }
    }

    public function addSection(): void
    {
        $this->guardEditable();
        $this->newSectionCounter++;
        $this->sections[] = ['key' => 'new'.uniqid(), 'id' => null, 'name' => 'New section', 'weight' => 1.0, 'fields' => []];
    }

    public function deleteSection(int $index): void
    {
        $this->guardEditable();
        if (! isset($this->sections[$index]) || $this->sections[$index]['fields'] !== []) {
            return;
        }
        array_splice($this->sections, $index, 1);
    }

    public function moveSection(int $index, int $direction): void
    {
        $this->guardEditable();
        $target = $index + $direction;
        if (! isset($this->sections[$index], $this->sections[$target])) {
            return;
        }
        [$this->sections[$index], $this->sections[$target]] = [$this->sections[$target], $this->sections[$index]];
    }

    /**
     * Move a field into a section at a position (used by drag-and-drop and the "Move to…" select).
     */
    public function moveField(int $fieldId, string $toSectionKey, ?int $position = null): void
    {
        $this->guardEditable();
        foreach ($this->sections as &$section) {
            $section['fields'] = array_values(array_filter($section['fields'], fn ($id) => (int) $id !== $fieldId));
        }
        unset($section);

        foreach ($this->sections as &$section) {
            if ($section['key'] === $toSectionKey) {
                $position ??= count($section['fields']);
                array_splice($section['fields'], max(0, min($position, count($section['fields']))), 0, [$fieldId]);
            }
        }
        unset($section);
    }

    /**
     * Full reorder of one section's fields (from SortableJS).
     *
     * @param  list<int|string>  $orderedIds
     */
    public function reorderSection(string $sectionKey, array $orderedIds): void
    {
        $this->guardEditable();
        $ids = array_map('intval', $orderedIds);
        foreach ($this->sections as &$section) {
            if ($section['key'] !== $sectionKey) {
                $section['fields'] = array_values(array_filter($section['fields'], fn ($id) => ! in_array((int) $id, $ids, true)));
            }
        }
        unset($section);
        foreach ($this->sections as &$section) {
            if ($section['key'] === $sectionKey) {
                $section['fields'] = $ids;
            }
        }
        unset($section);
    }

    public function setInfoKey(int $fieldId, ?string $infoKey): void
    {
        $this->guardEditable();
        if (! isset($this->fieldState[$fieldId])) {
            return;
        }
        $infoKey = $infoKey !== null && $infoKey !== '' ? $infoKey : null;
        if ($infoKey !== null && ! in_array($infoKey, InfoKey::values(), true)) {
            return;
        }
        $this->fieldState[$fieldId]['info_key'] = $infoKey;
        $this->resetErrorBag();
    }

    public function toggleScored(int $fieldId): void
    {
        $this->guardEditable();
        if (isset($this->fieldState[$fieldId])) {
            $this->fieldState[$fieldId]['is_scored'] = ! $this->fieldState[$fieldId]['is_scored'];
        }
    }

    public function save(MappingEditor $editor): void
    {
        $this->guardEditable();
        /** @var User $user */
        $user = auth()->user();

        try {
            $editor->save(
                $this->version,
                array_map(fn (array $s) => ['id' => $s['id'], 'name' => $s['name'], 'weight' => $s['weight'], 'fields' => $s['fields']], $this->sections),
                $this->fieldState,
                $user,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }

        $this->version->refresh();
        $this->loadState();

        $count = $this->version->submissions()->count();
        $this->rescoreCount = $count > 0 ? $count : null;

        session()->flash('status', 'Mapping saved.');
    }

    public function rescore(): void
    {
        $this->guardEditable();
        RescoreFormSubmissions::dispatch($this->form, $this->version);
        $this->rescoreCount = null;
        session()->flash('status', 'Recalculation started. Scores update in the background.');
    }

    public function dismissRescore(): void
    {
        $this->rescoreCount = null;
    }

    private function guardEditable(): void
    {
        abort_if($this->readOnly, 403, 'Only the current version can be edited.');
    }

    public function render(): View
    {
        $fields = $this->version->fields()->get()->keyBy('id');

        return view('livewire.admin.forms.mapping', [
            'fields' => $fields,
            'scorable' => $fields->mapWithKeys(fn (FormField $f) => [$f->id => MappingEditor::isScorable($f)]),
            'infoKeys' => InfoKey::cases(),
            'versions' => $this->form->versions()->get(['id', 'version_number', 'created_at', 'needs_review']),
        ]);
    }
}
