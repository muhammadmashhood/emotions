<?php

namespace App\Models\Concerns;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping. Every hospital-owned model uses this trait.
 *
 * @mixin Model
 */
trait BelongsToHospital
{
    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Restrict the query to rows the given user may see.
     * super_admin → everything; hospital users → their own hospital only.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where($query->getModel()->getTable().'.hospital_id', $user->hospital_id ?? 0);
    }

    public function scopeForHospital(Builder $query, Hospital|int $hospital): Builder
    {
        $id = $hospital instanceof Hospital ? $hospital->id : $hospital;

        return $query->where($query->getModel()->getTable().'.hospital_id', $id);
    }
}
