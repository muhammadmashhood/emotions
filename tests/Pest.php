<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit/Models');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

function hospitalAdmin(?Hospital $hospital = null): User
{
    $hospital ??= Hospital::factory()->create();

    return User::factory()->hospitalAdmin($hospital)->create();
}

function viewer(?Hospital $hospital = null): User
{
    $hospital ??= Hospital::factory()->create();

    return User::factory()->viewer($hospital)->create();
}

function fixture(string $name): array
{
    $path = __DIR__.'/Fixtures/typeform/'.$name;
    $docsPath = dirname(__DIR__).'/docs/fixtures/'.$name;
    $file = file_exists($docsPath) ? $docsPath : $path;

    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}
