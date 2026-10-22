<?php

use App\Domain\Forms\FormVersioner;
use App\Jobs\RescoreFormSubmissions;
use App\Livewire\Admin\Forms\Mapping;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->admin = superAdmin();
    $this->form = Form::factory()->create(['typeform_form_id' => 'rACXmOJ2']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_definition_rACXmOJ2.json'));
});

function sectionIndexByName(array $sections, string $name): int
{
    foreach ($sections as $i => $s) {
        if ($s['name'] === $name) {
            return $i;
        }
    }
    throw new RuntimeException("section $name not found");
}

test('admin fixes the misplaced website question, renames a section, toggles scored, sets weight and saves', function () {
    $website = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'The website/ App was easy to access')->firstOrFail();
    $agentName = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'The agent greeted you politely')->firstOrFail();

    $component = Livewire::actingAs($this->admin)->test(Mapping::class, ['form' => $this->form]);
    $sections = $component->get('sections');
    $websiteIdx = sectionIndexByName($sections, 'Website/ App booking');
    $arrivalIdx = sectionIndexByName($sections, 'Arrival & Reception');

    $component
        ->call('moveField', $website->id, $sections[$websiteIdx]['key'], 0)
        ->set("sections.$arrivalIdx.name", 'Arrival')
        ->set("sections.$websiteIdx.weight", 0.5)
        ->call('toggleScored', $agentName->id)
        ->call('setInfoKey', $agentName->id, '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rescoreCount', null);

    $website->refresh();
    expect($website->section->name)->toBe('Website/ App booking')
        ->and($website->display_order)->toBeLessThan(FormField::query()->where('section_id', $website->section_id)->where('id', '!=', $website->id)->min('display_order'))
        ->and($agentName->fresh()->is_scored)->toBeFalse()
        ->and($this->version->fresh()->needs_review)->toBeFalse()
        ->and($this->version->fresh()->reviewed_by)->toBe($this->admin->id);

    $arrival = $this->version->sections()->where('name', 'Arrival')->firstOrFail();
    expect((float) $this->version->sections()->where('name', 'Website/ App booking')->value('weight'))->toBe(0.5)
        ->and($arrival)->not->toBeNull();

    $log = AuditLog::query()->where('action', 'mapping.updated')->where('subject_id', $this->version->id)->firstOrFail();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->hospital_id)->toBe($this->form->hospital_id)
        ->and($log->changes['fields'][$website->id]['section'])->toBe(['General Information', 'Website/ App booking'])
        ->and($log->changes['fields'][$agentName->id]['is_scored'])->toBe([true, false])
        ->and(collect($log->changes['sections'])->pluck('name')->filter()->first())->toBe(['Arrival & Reception', 'Arrival']);
});

test('sections can be added, reordered and deleted only when empty', function () {
    $component = Livewire::actingAs($this->admin)->test(Mapping::class, ['form' => $this->form]);

    $component->call('addSection');
    $sections = $component->get('sections');
    expect($sections)->toHaveCount(11)->and($sections[10]['name'])->toBe('New section');

    $component->set('sections.10.name', 'Discharge')->call('moveSection', 10, -1);
    expect($component->get('sections')[9]['name'])->toBe('Discharge');

    // deleting a non-empty section is ignored
    $component->call('deleteSection', 0);
    expect($component->get('sections'))->toHaveCount(11);

    $component->call('save')->assertHasNoErrors();
    $saved = $this->version->sections()->get();
    expect($saved)->toHaveCount(11)
        ->and($saved->firstWhere('name', 'Discharge')->source_ref)->toStartWith(FormVersioner::ADMIN_SECTION_PREFIX)
        ->and($saved->firstWhere('name', 'Discharge')->display_order)->toBe(9);

    // delete the empty section again
    $component->call('deleteSection', 9)->call('save')->assertHasNoErrors();
    expect($this->version->sections()->count())->toBe(10);
});

test('validation rejects duplicate info keys, empty names and bad weights', function () {
    $facility = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Healthcare Facility')->firstOrFail();
    $location = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Location')->firstOrFail();

    $component = Livewire::actingAs($this->admin)->test(Mapping::class, ['form' => $this->form]);

    $component
        ->call('setInfoKey', $location->id, 'facility')
        ->call('save')
        ->assertHasErrors(["fields.{$location->id}.info_key"]);
    expect($location->fresh()->info_key)->toBe('location');

    $component
        ->call('setInfoKey', $location->id, 'location')
        ->set('sections.0.name', '')
        ->set('sections.1.weight', 11)
        ->call('save')
        ->assertHasErrors(['sections.0.name', 'sections.1.weight']);

    expect($facility->fresh()->info_key)->toBe('facility');
});

test('info keys are set explicitly per field and never leak to other fields', function () {
    $recommend = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'like', 'How likely are you to recommend%')->firstOrFail();
    $comments = FormField::query()->where('form_version_id', $this->version->id)->where('type', 'long_text')->firstOrFail();

    $component = Livewire::actingAs($this->admin)->test(Mapping::class, ['form' => $this->form])
        ->call('setInfoKey', $recommend->id, 'recommend')
        ->call('setInfoKey', $comments->id, '')
        ->call('setInfoKey', $comments->id, 'not-a-key');

    $state = $component->get('fieldState');
    expect($state[$recommend->id]['info_key'])->toBe('recommend')
        ->and($state[$comments->id]['info_key'])->toBeNull()
        ->and(collect($state)->filter(fn ($f) => $f['info_key'] === 'recommend'))->toHaveCount(1);

    $component->call('save')->assertHasNoErrors();
    expect($recommend->fresh()->info_key)->toBe('recommend')
        ->and(FormField::query()->where('form_version_id', $this->version->id)->where('info_key', 'recommend')->count())->toBe(1);

    // the KPI role may be shared by several questions; descriptive keys may not
    $rating = FormField::query()->where('form_version_id', $this->version->id)->where('title', 'Overall rating of your visit')->firstOrFail();
    $component->call('setInfoKey', $rating->id, 'recommend')->call('save')->assertHasNoErrors();
    expect(FormField::query()->where('form_version_id', $this->version->id)->where('info_key', 'recommend')->count())->toBe(2);
});

test('unscorable types cannot be scored even if toggled', function () {
    $comments = FormField::query()->where('form_version_id', $this->version->id)->where('type', 'long_text')->firstOrFail();

    Livewire::actingAs($this->admin)
        ->test(Mapping::class, ['form' => $this->form])
        ->call('toggleScored', $comments->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($comments->fresh()->is_scored)->toBeFalse();
});

test('after saving with existing evaluations the admin can dispatch a rescore', function () {
    Submission::factory()->count(3)->create(['form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->form->hospital_id]);

    Livewire::actingAs($this->admin)
        ->test(Mapping::class, ['form' => $this->form])
        ->set('sections.0.name', 'Intro')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rescoreCount', 3)
        ->assertSee('Recalculate scores for 3 evaluations')
        ->call('rescore')
        ->assertSet('rescoreCount', null);

    Queue::assertPushed(RescoreFormSubmissions::class, fn (RescoreFormSubmissions $job) => $job->form->is($this->form) && $job->version?->is($this->version));
});

test('older versions are read-only', function () {
    $v2 = app(FormVersioner::class)->createVersionFrom($this->form, fixture('form_definition_rACXmOJ2_v2.json'), $this->version);
    $this->form->forceFill(['current_version_id' => $v2->id])->save();

    Livewire::actingAs($this->admin)
        ->withQueryParams(['version' => 1])
        ->test(Mapping::class, ['form' => $this->form->fresh()])
        ->assertSet('readOnly', true)
        ->assertSee('read-only')
        ->call('addSection')
        ->assertForbidden();

    Livewire::withQueryParams([]);
    Livewire::actingAs($this->admin)
        ->test(Mapping::class, ['form' => $this->form->fresh()])
        ->assertSet('readOnly', false)
        ->assertSee('New'); // new field badge on v2
});

test('hospital users cannot open the mapping editor', function () {
    Livewire::actingAs(hospitalAdmin($this->form->hospital))
        ->test(Mapping::class, ['form' => $this->form])
        ->assertForbidden();
});
