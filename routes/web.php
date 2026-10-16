<?php

use App\Http\Controllers\EvaluationExportController;
use App\Http\Controllers\EvaluationFileController;
use App\Livewire\Admin;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    /** @var User $user */
    $user = auth()->user();

    return redirect()->route($user->isSuperAdmin() ? 'admin.overview' : 'dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('invitations/{token}', AcceptInvitation::class)->middleware('throttle:10,1')->name('invitation.accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', Hospital\Dashboard::class)->middleware('role:hospital_admin,hospital_viewer')->name('dashboard');
    Route::get('evaluations', Hospital\Evaluations\Index::class)->middleware('role:hospital_admin,hospital_viewer')->name('evaluations.index');
    Route::get('evaluations/export', EvaluationExportController::class)->name('evaluations.export');
    Route::get('evaluations/{evaluation}', Hospital\Evaluations\Show::class)->name('evaluations.show');
    Route::get('evaluations/{evaluation}/files/{answer}', EvaluationFileController::class)->whereNumber('answer')->name('evaluations.files.show');
    Route::view('profile', 'profile')->name('profile');
    Route::get('team', Hospital\Team::class)->middleware('role:hospital_admin')->name('team');

    // Admin "view as hospital": same components, explicit hospital.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('hospitals/{hospital}/dashboard', Hospital\Dashboard::class)->name('hospitals.dashboard');
        Route::get('hospitals/{hospital}/evaluations', Hospital\Evaluations\Index::class)->name('hospitals.evaluations.index');
    });
});

Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\Overview::class)->name('overview');
    Route::get('hospitals', Admin\Hospitals\Index::class)->name('hospitals.index');
    Route::get('hospitals/create', Admin\Hospitals\Create::class)->name('hospitals.create');
    Route::get('hospitals/{hospital}', Admin\Hospitals\Show::class)->name('hospitals.show');
    Route::get('hospitals/{hospital}/forms/link', Admin\Forms\LinkForm::class)->name('hospitals.forms.link');
    Route::get('forms/{form}', Admin\Forms\Show::class)->name('forms.show');
    Route::get('forms/{form}/mapping', Admin\Forms\Mapping::class)->name('forms.mapping');
    Route::get('deliveries', Admin\Deliveries\Index::class)->name('deliveries');
    Route::get('audit-log', Admin\AuditLog::class)->name('audit');
    Route::get('users', Admin\Users\Index::class)->name('users.index');
    Route::get('settings/typeform', Admin\Settings\Typeform::class)->name('settings.typeform');
});

require __DIR__.'/auth.php';
