<?php

use App\Domain\Forms\FormVersioner;
use App\Models\Form;
use App\Models\Hospital;
use Illuminate\Support\Facades\Http;

/**
 * Every screen renders for the right role with seeded data (no 500s).
 * Extended in each milestone as routes are added.
 */
test('routes render for their role', function (string $role, string $routeName, array $params = []) {
    Http::fake([
        '*/workspaces*' => Http::response(['items' => [], 'page_count' => 1]),
        '*/forms*' => Http::response(['items' => [], 'total_items' => 0, 'page_count' => 1]),
    ]);
    $hospital = Hospital::factory()->create(['name' => 'Smoke Hospital']);
    $form = Form::factory()->for($hospital)->create();
    app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
    $user = match ($role) {
        'super_admin' => superAdmin(),
        'hospital_admin' => hospitalAdmin($hospital),
        default => viewer($hospital),
    };

    $resolved = array_map(fn ($p) => match ($p) {
        ':hospital' => $hospital,
        ':form' => $form,
        default => $p,
    }, $params);

    $this->actingAs($user)->get(route($routeName, $resolved))->assertOk();
})->with([
    ['super_admin', 'admin.overview'],
    ['super_admin', 'admin.hospitals.index'],
    ['super_admin', 'admin.hospitals.create'],
    ['super_admin', 'admin.hospitals.show', [':hospital']],
    ['super_admin', 'admin.users.index'],
    ['super_admin', 'admin.settings.typeform'],
    ['super_admin', 'admin.hospitals.forms.link', [':hospital']],
    ['super_admin', 'admin.forms.show', [':form']],
    ['super_admin', 'admin.forms.mapping', [':form']],
    ['super_admin', 'profile'],
    ['hospital_admin', 'dashboard'],
    ['hospital_admin', 'team'],
    ['hospital_admin', 'profile'],
    ['hospital_viewer', 'dashboard'],
    ['hospital_viewer', 'profile'],
]);
