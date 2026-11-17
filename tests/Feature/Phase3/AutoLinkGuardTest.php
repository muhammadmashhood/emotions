<?php

use App\Domain\Forms\WorkspaceAutoLinker;
use App\Enums\WebhookStatus;
use App\Livewire\Admin\Hospitals\Show;
use App\Models\AuditLog;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\TypeformConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('mapping a workspace records when it was mapped and clearing it forgets', function () {
    TypeformConnection::factory()->create();
    $hospital = Hospital::factory()->create();
    Http::fake(['*/workspaces*' => Http::response(['items' => [['id' => 'ws-itt', 'name' => 'Ittefaq workspace']], 'page_count' => 1])]);
    Carbon::setTestNow('2026-11-17 10:00:00');

    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['hospital' => $hospital])
        ->set('tab', 'settings')
        ->set('workspace', 'ws-itt')
        ->call('saveSettings')
        ->assertHasNoErrors();
    expect($hospital->fresh()->typeform_workspace_mapped_at?->toDateTimeString())->toBe('2026-11-17 10:00:00');

    Carbon::setTestNow('2026-11-17 11:00:00');
    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['hospital' => $hospital->fresh()])
        ->set('tab', 'settings')
        ->set('name', 'Renamed')
        ->call('saveSettings')
        ->assertHasNoErrors();
    expect($hospital->fresh()->typeform_workspace_mapped_at?->toDateTimeString())->toBe('2026-11-17 10:00:00', 'unchanged mapping keeps its timestamp');

    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['hospital' => $hospital->fresh()])
        ->set('tab', 'settings')
        ->set('workspace', '')
        ->call('saveSettings')
        ->assertHasNoErrors();
    expect($hospital->fresh()->typeform_workspace_mapped_at)->toBeNull();
    Carbon::setTestNow();
});

test('auto-link only links forms created after the workspace was mapped', function () {
    Queue::fake();
    $connection = TypeformConnection::factory()->create();
    $mapped = Hospital::factory()->create([
        'typeform_workspace_id' => 'ws-itt',
        'typeform_workspace_name' => 'Ittefaq',
        'typeform_workspace_mapped_at' => '2026-11-17 10:00:00',
    ]);
    Http::fake([
        '*/forms?*' => Http::response(['total_items' => 3, 'page_count' => 1, 'items' => [
            ['id' => 'OLDFORM1', 'title' => 'Attendance form', 'created_at' => '2026-06-01T00:00:00Z', 'last_updated_at' => '2026-11-10T00:00:00Z'],
            ['id' => 'NODATE01', 'title' => 'Unknown age', 'last_updated_at' => '2026-11-18T00:00:00Z'],
            ['id' => 'rACXmOJ2', 'title' => 'New survey', 'created_at' => '2026-11-17T12:00:00Z', 'last_updated_at' => '2026-11-17T12:00:00Z'],
        ]]),
        '*/forms/rACXmOJ2' => Http::response(fixture('form_definition_rACXmOJ2.json')),
        '*/forms/rACXmOJ2/webhooks/*' => Http::response(['enabled' => true]),
    ]);

    expect(app(WorkspaceAutoLinker::class)->run())->toBe([$mapped->slug => 1])
        ->and(Form::query()->pluck('typeform_form_id')->all())->toBe(['rACXmOJ2']);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/forms/OLDFORM1'));
});

test('auto-link links nothing for a mapping with no recorded time', function () {
    Queue::fake();
    TypeformConnection::factory()->create();
    $mapped = Hospital::factory()->create(['typeform_workspace_id' => 'ws-itt', 'typeform_workspace_mapped_at' => null]);
    Http::fake(['*/forms?*' => Http::response(['total_items' => 1, 'page_count' => 1, 'items' => [
        ['id' => 'rACXmOJ2', 'title' => 'New survey', 'created_at' => '2026-11-17T12:00:00Z', 'last_updated_at' => '2026-11-17T12:00:00Z'],
    ]])]);

    expect(app(WorkspaceAutoLinker::class)->run())->toBe([$mapped->slug => 0])
        ->and(Form::query()->count())->toBe(0);
});

test('forms:purge-auto-linked lists automatic links on a dry run and removes only them with --force', function () {
    Queue::fake();
    $connection = TypeformConnection::factory()->create();
    $hospital = Hospital::factory()->create();
    $admin = superAdmin();

    $auto = Form::factory()->for($hospital)->create(['typeform_connection_id' => $connection->id, 'typeform_form_id' => 'AUTO0001', 'title' => 'Attendance form', 'linked_by' => null, 'webhook_status' => WebhookStatus::Active]);
    $manual = Form::factory()->for($hospital)->create(['typeform_connection_id' => $connection->id, 'typeform_form_id' => 'MANUAL01', 'title' => 'Real survey', 'linked_by' => $admin->id, 'webhook_status' => WebhookStatus::Active]);
    AuditLog::query()->create(['user_id' => null, 'hospital_id' => $hospital->id, 'action' => 'form.linked', 'subject_type' => Form::class, 'subject_id' => $auto->id, 'changes' => ['typeform_form_id' => 'AUTO0001', 'automatic' => true]]);
    AuditLog::query()->create(['user_id' => $admin->id, 'hospital_id' => $hospital->id, 'action' => 'form.linked', 'subject_type' => Form::class, 'subject_id' => $manual->id, 'changes' => ['typeform_form_id' => 'MANUAL01']]);
    $version = FormVersion::factory()->for($auto)->create();
    Submission::factory()->count(2)->create(['form_id' => $auto->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id]);
    Http::fake(['*/forms/*/webhooks/*' => Http::response([], 204)]);

    $this->artisan('forms:purge-auto-linked')
        ->expectsOutputToContain('Attendance form')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();
    expect(Form::withTrashed()->count())->toBe(2);
    Http::assertNothingSent();

    $this->artisan('forms:purge-auto-linked --force')
        ->expectsOutputToContain('Removed 1 form(s)')
        ->assertSuccessful();
    expect(Form::withTrashed()->pluck('typeform_form_id')->all())->toBe(['MANUAL01'])
        ->and(Submission::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'form.deleted')->where('changes->typeform_form_id', 'AUTO0001')->whereNull('user_id')->exists())->toBeTrue();
    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/forms/AUTO0001/webhooks/'));
});
