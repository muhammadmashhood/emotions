<?php

namespace App\Domain\Hospitals;

use App\Domain\Audit\AuditLogger;
use App\Domain\Forms\FormLinker;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hard-deletes a hospital account: Typeform webhooks are dropped first (errors are logged on the form, never fatal),
 * then every form, evaluation, user and invitation goes with it. Audit rows stay, detached from the hospital.
 */
class HospitalDeleter
{
    public function __construct(
        private readonly FormLinker $forms,
        private readonly AuditLogger $audit,
    ) {}

    public function delete(Hospital $hospital, User $by): void
    {
        $summary = [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'forms' => $hospital->forms()->count(),
            'submissions' => $hospital->submissions()->count(),
            'users' => $hospital->users()->count(),
        ];

        DB::transaction(function () use ($hospital): void {
            foreach ($hospital->forms()->get() as $form) {
                $this->forms->purge($form);
            }

            UserInvitation::query()->where('hospital_id', $hospital->id)->delete();
            $hospital->users()->delete();
            $hospital->branches()->delete();

            if ($hospital->logo_path !== null) {
                Storage::disk('public')->delete($hospital->logo_path);
            }

            $hospital->delete();
        });

        $this->audit->log('hospital.deleted', null, $summary, null, $by);
    }
}
