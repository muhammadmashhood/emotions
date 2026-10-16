<?php

namespace App\Domain\Dashboard;

use App\Enums\Rag;
use App\Enums\SubmissionStatus;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared query for the evaluations list and the CSV export (spec 9.2).
 */
class EvaluationListQuery
{
    public function __construct(private readonly User $viewer) {}

    /**
     * @return Builder<Submission>
     */
    public function build(
        ?Hospital $hospital,
        DashboardFilters $filters,
        ?string $rag = null,
        string $search = '',
        ?string $status = null,
        string $sort = 'date',
        string $direction = 'desc',
    ): Builder {
        $query = Submission::query()->viewableBy($this->viewer)->with(['form:id,title,uuid', 'branch:id,name', 'hospital:id,name']);

        if ($hospital !== null) {
            $query->forHospital($hospital);
        }

        $query->whereBetween('submissions.submitted_at', [$filters->from, $filters->to]);

        if ($filters->formId !== null) {
            $query->where('submissions.form_id', $filters->formId);
        }
        if ($filters->branchId !== null) {
            $query->where('submissions.branch_id', $filters->branchId);
        }
        if ($rag !== null && Rag::tryFrom($rag) !== null) {
            $query->where('submissions.rag', $rag);
        }
        if ($status !== null && $this->viewer->isSuperAdmin() && SubmissionStatus::tryFrom($status) !== null) {
            $query->where('submissions.status', $status);
        }
        if (trim($search) !== '') {
            $term = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], trim($search))).'%';
            $query->where(function (Builder $q) use ($term): void {
                foreach (['facility', 'doctor', 'location'] as $key) {
                    // JSON_EXTRACT returns a binary-collated string, so lower both sides for a case-insensitive match.
                    $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(submissions.info, '$.$key'))) LIKE ?", [$term]);
                }
            });
        }

        $dir = $direction === 'asc' ? 'asc' : 'desc';
        if ($sort === 'score') {
            $query->orderBy('submissions.overall_pct', $dir)->orderByDesc('submissions.submitted_at');
        } else {
            $query->orderBy('submissions.submitted_at', $dir)->orderByDesc('submissions.id');
        }

        return $query;
    }
}
