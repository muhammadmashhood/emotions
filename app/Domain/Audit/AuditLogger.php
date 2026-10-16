<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record an auditable action.
     *
     * @param  array<string, mixed>  $changes
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        Hospital|int|null $hospital = null,
        User|int|null $actor = null,
    ): AuditLog {
        $actorId = $actor instanceof User ? $actor->id : $actor;
        if ($actorId === null && Auth::check()) {
            $actorId = Auth::id();
        }

        $hospitalId = $hospital instanceof Hospital ? $hospital->id : $hospital;
        if ($hospitalId === null && $subject !== null && isset($subject->hospital_id)) {
            $hospitalId = (int) $subject->hospital_id;
        }
        if ($hospitalId === null && $subject instanceof Hospital) {
            $hospitalId = $subject->id;
        }

        return AuditLog::query()->create([
            'user_id' => $actorId,
            'hospital_id' => $hospitalId,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }
}
