<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationEvents;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use App\Policies\FormPolicy;
use App\Policies\HospitalPolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Gate::policy(Hospital::class, HospitalPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Form::class, FormPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by((string) $request->ip()));

        Event::listen(Login::class, [RecordAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Failed::class, [RecordAuthenticationEvents::class, 'handleFailed']);
    }
}
