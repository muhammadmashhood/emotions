<?php

use App\Livewire\Hospital\Dashboard;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $form = Form::factory()->for($this->hospital)->create(['title' => 'OPD survey']);
    $version = FormVersion::factory()->create(['form_id' => $form->id]);
    $this->low = Submission::factory()->create([
        'form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $this->hospital->id,
        'submitted_at' => now()->subDays(3), 'overall_pct' => 41.0, 'rag' => 'red', 'info' => ['facility' => 'Al Barsha', 'doctor' => 'Dr Rahim'],
    ]);
});

test('clicking a heat-map cell lists the evaluations behind it with a link to each report', function () {
    Livewire::actingAs(viewer($this->hospital))->test(Dashboard::class)
        ->assertSee('Al Barsha')
        ->assertDontSee('Dr Rahim')
        ->call('selectCell', 'Al Barsha', 'OPD survey')
        ->assertSee('Dr Rahim')
        ->assertSee('41%')
        ->assertSee(route('evaluations.show', $this->low), false)
        ->call('clearCell')
        ->assertDontSee('Dr Rahim');
});

test('a cell with no evaluations says so instead of erroring', function () {
    Livewire::actingAs(viewer($this->hospital))->test(Dashboard::class)
        ->call('selectCell', 'Nowhere', 'OPD survey')
        ->assertSee('No scored evaluations for this cell.');
});
