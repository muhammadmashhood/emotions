<?php

use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;

test('purging demo data removes demo forms and demo-only hospitals but keeps real ones', function () {
    $demoHospital = Hospital::factory()->create(['name' => 'Ittefaq Hospital', 'slug' => 'ittefaq']);
    $demoForm = Form::factory()->for($demoHospital)->create(['typeform_form_id' => 'demoITT1']);
    $demoVersion = FormVersion::factory()->create(['form_id' => $demoForm->id]);
    Submission::factory()->count(3)->create(['form_id' => $demoForm->id, 'form_version_id' => $demoVersion->id, 'hospital_id' => $demoHospital->id]);
    $demoUser = User::factory()->viewer($demoHospital)->create();

    $real = Hospital::factory()->create(['name' => 'Falcon Hospital']);
    $realForm = Form::factory()->for($real)->create(['typeform_form_id' => 'FxTLQ7Cv']);
    $realVersion = FormVersion::factory()->create(['form_id' => $realForm->id]);
    Submission::factory()->create(['form_id' => $realForm->id, 'form_version_id' => $realVersion->id, 'hospital_id' => $real->id]);
    $admin = superAdmin();

    $this->artisan('app:purge-demo-data', ['--force' => true])->assertSuccessful();

    expect(Form::withTrashed()->where('typeform_form_id', 'like', 'demo%')->count())->toBe(0)
        ->and(Hospital::query()->where('slug', 'ittefaq')->exists())->toBeFalse()
        ->and(User::query()->whereKey($demoUser->id)->exists())->toBeFalse()
        ->and(Submission::query()->count())->toBe(1)
        ->and(Hospital::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and(User::query()->whereKey($admin->id)->exists())->toBeTrue();

    $this->artisan('app:purge-demo-data', ['--force' => true])->expectsOutputToContain('No demo data found');
});
