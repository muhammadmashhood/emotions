<?php

use App\Livewire\Admin\Forms\LinkForm;
use App\Livewire\Admin\Settings\Typeform;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('saving a valid token verifies it with /me and stores it encrypted', function () {
    Http::fake(['https://api.eu.typeform.com/me' => Http::response(['alias' => 'acme-eu', 'email' => 'x@y.z'])]);
    $admin = superAdmin();

    Livewire::actingAs($admin)
        ->test(Typeform::class)
        ->set('label', 'EU account')
        ->set('base_url', 'https://api.eu.typeform.com')
        ->set('token', 'tfp_abcdefghijklmnopqrstuvwxyz1234')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('token', '');

    $connection = TypeformConnection::query()->firstOrFail();
    expect($connection->access_token)->toBe('tfp_abcdefghijklmnopqrstuvwxyz1234')
        ->and($connection->getRawOriginal('access_token'))->not->toContain('tfp_abcdefghijklmnopqrstuvwxyz1234')
        ->and($connection->account_alias)->toBe('acme-eu')
        ->and($connection->last_verified_at)->not->toBeNull()
        ->and($connection->maskedToken())->toBe('tfp_…1234');

    $this->assertDatabaseHas('audit_logs', ['action' => 'typeform.connection_saved', 'user_id' => $admin->id]);
    $this->assertDatabaseMissing('audit_logs', ['changes' => 'tfp_abcdefghijklmnopqrstuvwxyz1234']);
});

test('an invalid token is not saved', function () {
    Http::fake(['*/me' => Http::response(['code' => 'AUTHENTICATION_FAILED'], 401)]);

    Livewire::actingAs(superAdmin())
        ->test(Typeform::class)
        ->set('token', 'tfp_invalid_token_value_000000')
        ->call('save')
        ->assertHasErrors(['token']);

    expect(TypeformConnection::query()->count())->toBe(0);
});

test('test connection records failures', function () {
    $connection = TypeformConnection::factory()->create();
    Http::fake(['*/me' => Http::response('down', 500)]);

    Livewire::actingAs(superAdmin())
        ->test(Typeform::class)
        ->call('test');

    expect($connection->fresh()->last_error)->toContain('500');
});

test('settings page renders without a connection', function () {
    $this->actingAs(superAdmin())->get(route('admin.settings.typeform'))->assertOk()->assertSee('Connect Typeform');
});

test('form picker lists forms per workspace, excludes linked ones and passes the search term', function () {
    $connection = TypeformConnection::factory()->create();
    $hospital = Hospital::factory()->create();
    $other = Hospital::factory()->create();

    // Already linked elsewhere: excluded. Unlinked from this hospital: available again.
    Form::factory()->for($other)->create(['typeform_connection_id' => $connection->id, 'typeform_form_id' => 'LINKED1']);
    Form::factory()->for($hospital)->unlinked()->create(['typeform_connection_id' => $connection->id, 'typeform_form_id' => 'RELINK']);

    Http::fake([
        '*/workspaces*' => Http::response(['items' => [['id' => 'ws1', 'name' => 'Hospitals', 'default' => true]], 'page_count' => 1]),
        '*/forms*' => Http::response(['total_items' => 3, 'page_count' => 1, 'items' => [
            ['id' => 'LINKED1', 'title' => 'Taken', 'last_updated_at' => '2026-01-03T00:00:00Z'],
            ['id' => 'RELINK', 'title' => 'Relinkable', 'last_updated_at' => '2026-01-02T00:00:00Z'],
            ['id' => 'NEW1', 'title' => 'Ittefaq mystery shopping', 'last_updated_at' => '2026-01-01T00:00:00Z'],
        ]]),
    ]);

    Livewire::actingAs(superAdmin())
        ->test(LinkForm::class, ['hospital' => $hospital])
        ->set('search', 'ittefaq')
        ->assertSee('Ittefaq mystery shopping')
        ->assertSee('Relinkable')
        ->assertDontSee('Taken')
        ->assertSee('Hospitals')
        ->call('select', 'NEW1')
        ->assertSee('Link form')
        ->assertSee('to <strong>'.$hospital->name, false);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/forms') && str_contains($r->url(), 'search=ittefaq') && str_contains($r->url(), 'workspace_id=ws1'));
});

test('form picker shows an error banner when Typeform is unreachable', function () {
    TypeformConnection::factory()->create();
    Http::fake(['*' => Http::response('nope', 503)]);

    Livewire::actingAs(superAdmin())
        ->test(LinkForm::class, ['hospital' => Hospital::factory()->create()])
        ->assertSee('Could not load forms from Typeform');
});
