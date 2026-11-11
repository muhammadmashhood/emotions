<?php

use App\Livewire\Hospital\Dashboard;
use App\Livewire\Hospital\Evaluations\Index;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Support\ScoreBand;
use Livewire\Livewire;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $this->form = Form::factory()->for($this->hospital)->create(['title' => 'OPD survey']);
    $this->version = FormVersion::factory()->create(['form_id' => $this->form->id]);
    $make = fn (float $pct, string $rag, string $facility, int $daysAgo) => Submission::factory()->create([
        'form_id' => $this->form->id, 'form_version_id' => $this->version->id, 'hospital_id' => $this->hospital->id,
        'submitted_at' => now()->subDays($daysAgo), 'overall_pct' => $pct, 'rag' => $rag, 'info' => ['facility' => $facility],
    ]);
    $make(91.0, 'green', 'Good Clinic', 3);
    $make(52.0, 'red', 'Poor Clinic', 5);
    $make(70.0, 'amber', 'Old Clinic', 200);
});

test('the dashboard shows the design widgets and score bands', function () {
    Livewire::actingAs(viewer($this->hospital))->test(Dashboard::class)
        ->assertSee($this->hospital->name.' Mystery Shopping Dashboard')
        ->assertSee('Mystery shopping')
        ->assertSee('Score bands')
        ->assertSee('Good ≥ 80%')
        ->assertSee('Visits completed')
        ->assertSee('Overall score trend')
        ->assertSee('Outcome mix')
        ->assertSee('rated Good')
        ->assertSee('Touchpoint heat-map')
        ->assertSee('Live evaluation feed')
        ->assertSee('1 poor visit needs attention');
});

test('the evaluations list filters by band chip and period, and links each row', function () {
    $component = Livewire::actingAs(viewer($this->hospital))->test(Index::class)
        ->assertSee('Good Clinic')
        ->assertSee('Poor Clinic')
        ->assertDontSee('Old Clinic')
        ->assertSee('Showing 1–2 of 2')
        ->set('rag', 'red')
        ->assertSee('Poor Clinic')
        ->assertDontSee('Good Clinic')
        ->set('rag', '')
        ->call('setPeriod', '12m')
        ->assertSet('period', '12m')
        ->assertSee('Old Clinic');

    $component->assertSee(route('evaluations.show', Submission::query()->where('overall_pct', 91.0)->firstOrFail()), false);
});

test('score bands follow the form thresholds and the design labels', function () {
    expect(ScoreBand::of(85)->label())->toBe('Good')
        ->and(ScoreBand::of(70)->label())->toBe('Watch')
        ->and(ScoreBand::of(40)->tone())->toBe('bad')
        ->and(ScoreBand::of(85, 90, 70)->label())->toBe('Watch')
        ->and(ScoreBand::of(null)->label())->toBe('No score');
});

test('the sign-in screen uses the design', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Sign in')
        ->assertSee('Every visit measured.')
        ->assertSee('Keep me signed in')
        ->assertSee('Forgot password?')
        ->assertSee('data-login-viz', false);
});
