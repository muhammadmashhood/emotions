<?php

use App\Domain\Forms\FormVersioner;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;
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
    $version = app(FormVersioner::class)->createInitialVersion($form, fixture('form_definition_rACXmOJ2.json'));
    $submission = Submission::factory()->create(['form_id' => $form->id, 'form_version_id' => $version->id, 'hospital_id' => $hospital->id, 'submitted_at' => now()->subDay()]);
    SubmissionSectionScore::factory()->create(['submission_id' => $submission->id, 'section_id' => $version->sections()->first()->id, 'section_name' => 'General Information']);
    $user = match ($role) {
        'super_admin' => superAdmin(),
        'hospital_admin' => hospitalAdmin($hospital),
        default => viewer($hospital),
    };

    $resolved = array_map(fn ($p) => match ($p) {
        ':hospital' => $hospital,
        ':form' => $form,
        ':submission' => $submission,
        default => $p,
    }, $params);

    $this->actingAs($user)->get(route($routeName, $resolved))->assertOk();
})->with([
    ['super_admin', 'admin.overview'],
    ['super_admin', 'admin.hospitals.index'],
    ['super_admin', 'admin.hospitals.create'],
    ['super_admin', 'admin.hospitals.show', [':hospital']],
    ['super_admin', 'admin.users.index'],
    ['super_admin', 'admin.deliveries'],
    ['super_admin', 'admin.audit'],
    ['super_admin', 'admin.settings.typeform'],
    ['super_admin', 'admin.hospitals.forms.link', [':hospital']],
    ['super_admin', 'admin.forms.show', [':form']],
    ['super_admin', 'admin.forms.mapping', [':form']],
    ['super_admin', 'profile'],
    ['super_admin', 'hospitals.dashboard', [':hospital']],
    ['super_admin', 'hospitals.evaluations.index', [':hospital']],
    ['super_admin', 'evaluations.show', [':submission']],
    ['hospital_admin', 'dashboard'],
    ['hospital_admin', 'evaluations.index'],
    ['hospital_admin', 'evaluations.show', [':submission']],
    ['hospital_admin', 'team'],
    ['hospital_admin', 'profile'],
    ['hospital_viewer', 'dashboard'],
    ['hospital_viewer', 'evaluations.index'],
    ['hospital_viewer', 'evaluations.show', [':submission']],
    ['hospital_viewer', 'profile'],
]);
