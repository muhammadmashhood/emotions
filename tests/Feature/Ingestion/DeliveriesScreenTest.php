<?php

use App\Jobs\ProcessTypeformResponse;
use App\Livewire\Admin\Deliveries\Index;
use App\Models\Form;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('deliveries screen lists, filters, shows payloads and retries failed deliveries', function () {
    Queue::fake();
    $form = Form::factory()->create(['title' => 'Survey A']);
    $ok = WebhookDelivery::factory()->create(['form_id' => $form->id, 'processed_at' => now(), 'payload' => '{"event_id":"ok-1"}']);
    $failed = WebhookDelivery::factory()->create(['form_id' => $form->id, 'error' => 'Boom', 'payload' => '{"event_id":"fail-1"}']);
    $invalid = WebhookDelivery::factory()->create(['form_id' => null, 'signature_valid' => false]);

    $component = Livewire::actingAs(superAdmin())->test(Index::class)
        ->assertSee('Survey A')
        ->assertSee('Boom')
        ->assertSee('Rejected')
        ->set('status', 'error')
        ->assertSee('Boom')
        ->assertDontSee('Rejected')
        ->set('status', '')
        ->call('view', $failed->id)
        ->assertSee('fail-1')
        ->call('retry', $failed->id);

    expect($failed->fresh()->error)->toBeNull();
    Queue::assertPushed(ProcessTypeformResponse::class, fn ($job) => $job->deliveryId === $failed->id);

    // invalid deliveries cannot be retried
    $component->call('retry', $invalid->id);
    Queue::assertPushed(ProcessTypeformResponse::class, 1);
});

test('hospital users cannot open the deliveries screen', function () {
    $this->actingAs(hospitalAdmin())->get(route('admin.deliveries'))->assertForbidden();
});
