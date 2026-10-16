<?php

use App\Enums\WebhookStatus;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\Overview;
use App\Models\AuditLog as AuditLogModel;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\TypeformConnection;
use App\Models\WebhookDelivery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('overview lists hospitals with evaluations, averages, trend and problem counts', function () {
    TypeformConnection::factory()->create();
    $ittefaq = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $cmh = Hospital::factory()->create(['name' => 'CMH']);
    $form = Form::factory()->for($ittefaq)->create(['title' => 'OPD']);
    $version = FormVersion::factory()->create(['form_id' => $form->id, 'needs_review' => true]);
    $form->forceFill(['current_version_id' => $version->id])->save();

    Submission::factory()->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $ittefaq->id, 'submitted_at' => now()->subDays(5), 'overall_pct' => 80]);
    Submission::factory()->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $ittefaq->id, 'submitted_at' => now()->subDays(100), 'overall_pct' => 70]);

    Livewire::actingAs(superAdmin())
        ->test(Overview::class)
        ->assertSee('Ittefaq Hospital')
        ->assertSee('CMH')
        ->assertSee('80.0%')
        ->assertSee('▲ +10.0')
        ->assertSee('Mapping review')
        ->assertSee('Alerts (1)')
        ->call('setPeriod', '30d')
        ->assertSee('last 30 days');
});

test('a deleted webhook shows an alert and "Repair webhook" fixes it', function () {
    TypeformConnection::factory()->create();
    $form = Form::factory()->create(['title' => 'Broken form', 'typeform_form_id' => 'BRK1', 'webhook_status' => WebhookStatus::Missing, 'webhook_last_error' => null]);
    FormVersion::factory()->create(['form_id' => $form->id])->form->forceFill(['current_version_id' => $form->versions()->first()->id])->save();

    Http::fake(['*/forms/BRK1/webhooks/*' => Http::sequence()->push(null, 404)->push(['enabled' => true])]);

    Livewire::actingAs(superAdmin())
        ->test(Overview::class)
        ->assertSee('Webhook Missing')
        ->assertSee('Broken form')
        ->call('repairWebhook', $form->uuid)
        ->assertDontSee('Webhook Missing');

    expect($form->fresh()->webhook_status)->toBe(WebhookStatus::Active);
    Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['url'] === $form->webhookUrl());
    $this->assertDatabaseHas('audit_logs', ['action' => 'webhook.repaired', 'subject_id' => $form->id]);
});

test('overview alerts include failed deliveries and connection problems', function () {
    $connection = TypeformConnection::factory()->create(['last_error' => 'Typeform responded 401']);
    $form = Form::factory()->create(['typeform_connection_id' => $connection->id]);
    WebhookDelivery::factory()->create(['form_id' => $form->id, 'error' => 'Boom', 'received_at' => now()->subHours(2)]);
    WebhookDelivery::factory()->create(['form_id' => $form->id, 'error' => 'Old', 'received_at' => now()->subDays(3)]);
    WebhookDelivery::factory()->create(['form_id' => null, 'signature_valid' => false, 'received_at' => now()->subHour()]);

    Livewire::actingAs(superAdmin())
        ->test(Overview::class)
        ->assertSee('Delivery failed')
        ->assertSee('Boom')
        ->assertDontSee('Old')
        ->assertSee('Connection error')
        ->assertSee('1 request with an invalid signature');
});

test('overview without a connection prompts to connect', function () {
    Livewire::actingAs(superAdmin())->test(Overview::class)->assertSee('No Typeform connection configured');
});

test('audit log screen filters by action, hospital, user and date', function () {
    $hospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital']);
    $admin = superAdmin();
    $other = hospitalAdmin();
    AuditLogModel::factory()->create(['action' => 'form.linked', 'hospital_id' => $hospital->id, 'user_id' => $admin->id, 'changes' => ['title' => 'OPD'], 'created_at' => now()->subDays(2)]);
    AuditLogModel::factory()->create(['action' => 'user.invited', 'hospital_id' => null, 'user_id' => $other->id, 'created_at' => now()->subDays(10)]);

    Livewire::actingAs($admin)
        ->test(AuditLog::class)
        ->assertSee('form.linked')
        ->assertSee('user.invited')
        ->set('action', 'form')
        ->assertSee('form.linked')
        ->assertDontSee('user.invited')
        ->set('action', '')
        ->set('hospital', $hospital->uuid)
        ->assertSee('form.linked')
        ->assertDontSee('user.invited')
        ->set('hospital', '')
        ->set('user', $other->email)
        ->assertSee('user.invited')
        ->assertDontSee('form.linked')
        ->set('user', '')
        ->set('from', now()->subDays(5)->toDateString())
        ->assertSee('form.linked')
        ->assertDontSee('user.invited')
        ->call('toggle', AuditLogModel::query()->where('action', 'form.linked')->value('id'))
        ->assertSee('&quot;title&quot;: &quot;OPD&quot;', false);
});

test('hospital users cannot open the audit log', function () {
    $this->actingAs(hospitalAdmin())->get(route('admin.audit'))->assertForbidden();
});
