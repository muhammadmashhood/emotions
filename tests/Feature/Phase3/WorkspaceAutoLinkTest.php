<?php

use App\Domain\Forms\WorkspaceAutoLinker;
use App\Livewire\Admin\Hospitals\Show;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('admin maps a hospital to a Typeform workspace', function () {
    TypeformConnection::factory()->create();
    $hospital = Hospital::factory()->create();
    Http::fake(['*/workspaces*' => Http::response(['items' => [['id' => 'ws-itt', 'name' => 'Ittefaq workspace']], 'page_count' => 1])]);

    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['hospital' => $hospital])
        ->set('tab', 'settings')
        ->assertSee('Ittefaq workspace')
        ->set('workspace', 'ws-itt')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect($hospital->fresh()->typeform_workspace_id)->toBe('ws-itt')
        ->and($hospital->fresh()->typeform_workspace_name)->toBe('Ittefaq workspace');
});

test('the scheduled auto-link job links new forms in mapped workspaces with needs_review', function () {
    Queue::fake();
    $connection = TypeformConnection::factory()->create();
    $mapped = Hospital::factory()->create(['typeform_workspace_id' => 'ws-itt', 'typeform_workspace_name' => 'Ittefaq']);
    Hospital::factory()->create(); // unmapped: ignored
    Form::factory()->for($mapped)->create(['typeform_connection_id' => $connection->id, 'typeform_form_id' => 'ALREADY']);

    Http::fake([
        '*/forms?*' => Http::response(['total_items' => 2, 'page_count' => 1, 'items' => [
            ['id' => 'ALREADY', 'title' => 'Old', 'last_updated_at' => '2026-01-01T00:00:00Z'],
            ['id' => 'rACXmOJ2', 'title' => 'New survey', 'last_updated_at' => '2026-02-01T00:00:00Z'],
        ]]),
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::response(['enabled' => true]),
    ]);

    $this->artisan('forms:auto-link')->assertSuccessful()->expectsOutputToContain($mapped->slug.': 1 new form(s) linked');

    $form = Form::query()->where('typeform_form_id', 'rACXmOJ2')->firstOrFail();
    expect($form->hospital_id)->toBe($mapped->id)
        ->and($form->linked_by)->toBeNull()
        ->and($form->currentVersion->needs_review)->toBeTrue()
        ->and(Form::query()->count())->toBe(2);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'workspace_id=ws-itt'));
    expect(AuditLog::query()->where('action', 'form.linked')->where('changes->automatic', true)->exists())->toBeTrue();

    // second run: nothing new
    expect(app(WorkspaceAutoLinker::class)->run())->toBe([$mapped->slug => 0]);
});

test('auto-link is scheduled hourly', function () {
    $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode(' ');
    expect($events)->toContain('forms:auto-link');
});
