<?php

use App\Domain\Forms\FormVersioner;
use App\Livewire\Admin\Forms\Show as FormShow;
use App\Livewire\Admin\Hospitals\Show as HospitalShow;
use App\Models\Branch;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use Livewire\Livewire;

/**
 * Staff pages show the same evaluation counts as the dashboard: approved only. Pending and rejected
 * rows are mentioned separately and still count towards delete / rescore confirmations.
 */
beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $this->form = Form::factory()->for($this->hospital)->create(['title' => 'OPD']);
    $this->version = app(FormVersioner::class)->createInitialVersion($this->form, fixture('form_rating_yesno.json'));
    $this->branch = Branch::factory()->create(['hospital_id' => $this->hospital->id, 'name' => 'Main campus']);

    $base = ['form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->hospital->id, 'branch_id' => $this->branch->id];
    Submission::factory()->count(2)->create($base);
    Submission::factory()->rejected()->create($base);
    Submission::factory()->pendingReview()->create($base);
});

test('hospital page counts approved evaluations and keeps the full count for deletion', function () {
    $admin = superAdmin();

    Livewire::actingAs($admin)->test(HospitalShow::class, ['hospital' => $this->hospital])
        ->assertSeeInOrder(['Evaluations', '2', '2 pending or rejected, not counted']);
    Livewire::actingAs($admin)->test(HospitalShow::class, ['hospital' => $this->hospital, 'tab' => 'forms'])
        ->assertSeeInOrder(['Rating and yes/no', 'Active', '2'])->assertSee('its 4 evaluations?');
    Livewire::actingAs($admin)->test(HospitalShow::class, ['hospital' => $this->hospital, 'tab' => 'facilities'])
        ->assertSeeInOrder(['Main campus', 'Active', '2']);
});

test('form page counts approved evaluations and keeps the full count for rescore and deletion', function () {
    Livewire::actingAs(superAdmin())
        ->test(FormShow::class, ['form' => $this->form])
        ->assertSeeInOrder(['Evaluations', '2', '2 pending or rejected, not counted'])
        ->assertSee('Recalculate scores for 4 evaluations?')
        ->assertSee('its 4 evaluations?');
});
