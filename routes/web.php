<?php

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
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
    Route::get('team', Hospital\Team::class)->middleware('role:hospital_admin')->name('team');
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
    Route::get('users', Admin\Users\Index::class)->name('users.index');
    Route::get('settings/typeform', Admin\Settings\Typeform::class)->name('settings.typeform');
});

require __DIR__.'/auth.php';
