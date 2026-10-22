<?php

namespace App\Domain\Dashboard;

use App\Models\Form;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All dashboard aggregates. Always scoped to a hospital (or to everything for the admin overview) and
 * always filtered by the viewer's visibility rules (spec section 8 / 9). Reads only our database.
 */
class DashboardQuery
{
    /**
     * How an evaluation is attributed to a facility: the resolved branch, else the "facility" info field,
     * else the "location" info field (hospital groups often identify the site by city).
     */
    public const FACILITY_EXPR = "COALESCE(branches.name, NULLIF(JSON_UNQUOTE(JSON_EXTRACT(submissions.info, '$.facility')), 'null'), NULLIF(JSON_UNQUOTE(JSON_EXTRACT(submissions.info, '$.location')), 'null'), 'Unknown facility')";

    public function __construct(private readonly User $viewer) {}

    /**
     * @return Builder<Submission>
     */
    public function base(?Hospital $hospital, DashboardFilters $filters): Builder
    {
        $query = Submission::query()->viewableBy($this->viewer);

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

        return $query;
    }

    /**
     * @return array{avg: float|null, count: int, previous_avg: float|null, previous_count: int, delta: float|null}
     */
    public function headline(?Hospital $hospital, DashboardFilters $filters): array
    {
        $current = $this->base($hospital, $filters)->toBase()->selectRaw('AVG(overall_pct) as avg, COUNT(*) as cnt')->first();
        $previous = $this->base($hospital, $filters->previous())->toBase()->selectRaw('AVG(overall_pct) as avg, COUNT(*) as cnt')->first();

        $avg = $current?->avg !== null ? round((float) $current->avg, 1) : null;
        $prevAvg = $previous?->avg !== null ? round((float) $previous->avg, 1) : null;

        return [
            'avg' => $avg,
            'count' => (int) ($current->cnt ?? 0),
            'previous_avg' => $prevAvg,
            'previous_count' => (int) ($previous->cnt ?? 0),
            'delta' => $avg !== null && $prevAvg !== null ? round($avg - $prevAvg, 1) : null,
        ];
    }

    /**
     * Average overall score per week (or month), one series per form when no form is selected (max 6 + "Other").
     *
     * @return array{bucket: string, labels: list<string>, series: list<array{label: string, data: list<float|null>, counts: list<int>}>}
     */
    public function trend(?Hospital $hospital, DashboardFilters $filters): array
    {
        $bucket = $filters->bucket();
        $periodExpr = $bucket === 'month'
            ? "DATE_FORMAT(submissions.submitted_at, '%Y-%m')"
            : "DATE_FORMAT(submissions.submitted_at, '%x-W%v')";

        $rows = $this->base($hospital, $filters)
            ->join('forms', 'forms.id', '=', 'submissions.form_id')
            ->selectRaw("$periodExpr as period, submissions.form_id, forms.title as form_title, AVG(submissions.overall_pct) as avg, COUNT(*) as cnt")
            ->whereNotNull('submissions.overall_pct')
            ->groupBy('period', 'submissions.form_id', 'forms.title')
            ->orderBy('period')
            ->toBase()
            ->get();

        $labels = $this->periods($filters, $bucket);

        if ($filters->formId !== null || $rows->pluck('form_id')->unique()->count() <= 1) {
            $byPeriod = $rows->groupBy('period');
            $data = [];
            $counts = [];
            foreach ($labels as $label) {
                $group = $byPeriod->get($label);
                if ($group === null) {
                    $data[] = null;
                    $counts[] = 0;

                    continue;
                }
                $n = (int) $group->sum('cnt');
                $data[] = $n > 0 ? round((float) $group->sum(fn ($r) => $r->avg * $r->cnt) / $n, 1) : null;
                $counts[] = $n;
            }

            return ['bucket' => $bucket, 'labels' => $labels, 'series' => [['label' => 'Average score', 'data' => $data, 'counts' => $counts]]];
        }

        $totalsByForm = $rows->groupBy('form_id')->map(fn ($g) => (int) $g->sum('cnt'))->sortDesc();
        $topForms = $totalsByForm->keys()->take(6)->all();

        $series = [];
        foreach ($topForms as $formId) {
            $formRows = $rows->where('form_id', $formId)->keyBy('period');
            $series[] = [
                'label' => (string) $rows->firstWhere('form_id', $formId)?->form_title,
                'data' => array_map(fn ($l) => isset($formRows[$l]) ? round((float) $formRows[$l]->avg, 1) : null, $labels),
                'counts' => array_map(fn ($l) => isset($formRows[$l]) ? (int) $formRows[$l]->cnt : 0, $labels),
            ];
        }

        $otherRows = $rows->whereNotIn('form_id', $topForms);
        if ($otherRows->isNotEmpty()) {
            $byPeriod = $otherRows->groupBy('period');
            $series[] = [
                'label' => 'Other',
                'data' => array_map(function ($l) use ($byPeriod) {
                    $g = $byPeriod->get($l);
                    if ($g === null) {
                        return null;
                    }
                    $n = (int) $g->sum('cnt');

                    return $n > 0 ? round((float) $g->sum(fn ($r) => $r->avg * $r->cnt) / $n, 1) : null;
                }, $labels),
                'counts' => array_map(fn ($l) => (int) ($byPeriod->get($l)?->sum('cnt') ?? 0), $labels),
            ];
        }

        return ['bucket' => $bucket, 'labels' => $labels, 'series' => $series];
    }

    /**
     * Average pct per section (grouped by section name), in the current version's order, with evaluation counts.
     *
     * @return list<array{name: string, avg: float, count: int}>
     */
    public function sectionAverages(Form $form, DashboardFilters $filters): array
    {
        $rows = $this->base($form->hospital, $filters->withForm($form->id))
            ->join('submission_section_scores as sss', 'sss.submission_id', '=', 'submissions.id')
            ->whereNotNull('sss.pct')
            ->selectRaw('sss.section_name as name, AVG(sss.pct) as avg, COUNT(*) as cnt')
            ->groupBy('sss.section_name')
            ->toBase()
            ->get();

        $order = $form->currentVersion?->sections()->pluck('display_order', 'name')->all() ?? [];

        return $rows
            ->map(fn ($r) => ['name' => (string) $r->name, 'avg' => round((float) $r->avg, 1), 'count' => (int) $r->cnt])
            ->sortBy(fn ($r) => $order[$r['name']] ?? 999)
            ->values()
            ->all();
    }

    /**
     * The 10 scored questions with the lowest average points / max_points.
     *
     * @return list<array{field_id: string, title: string, section: string|null, avg_pct: float, count: int}>
     */
    public function weakestQuestions(Form $form, DashboardFilters $filters, int $limit = 5): array
    {
        return $this->rankedQuestions($form, $filters, $limit, 'asc');
    }

    /**
     * The scored questions with the highest average points / max_points.
     *
     * @return list<array{field_id: string, title: string, section: string|null, avg_pct: float, count: int}>
     */
    public function strongestQuestions(Form $form, DashboardFilters $filters, int $limit = 5): array
    {
        return $this->rankedQuestions($form, $filters, $limit, 'desc');
    }

    /**
     * @return list<array{field_id: string, title: string, section: string|null, avg_pct: float, count: int}>
     */
    private function rankedQuestions(Form $form, DashboardFilters $filters, int $limit, string $direction): array
    {
        $rows = $this->base($form->hospital, $filters->withForm($form->id))
            ->join('answers', 'answers.submission_id', '=', 'submissions.id')
            ->whereNotNull('answers.points')
            ->where('answers.max_points', '>', 0)
            ->selectRaw('answers.typeform_field_id as field_id, SUM(answers.points) as points, SUM(answers.max_points) as max_points, COUNT(*) as cnt')
            ->groupBy('answers.typeform_field_id')
            ->toBase()
            ->get();

        $titles = $this->fieldTitles($form, $rows->pluck('field_id')->all());

        return $rows
            ->map(fn ($r) => [
                'field_id' => (string) $r->field_id,
                'title' => $titles[$r->field_id]['title'] ?? (string) $r->field_id,
                'section' => $titles[$r->field_id]['section'] ?? null,
                'avg_pct' => round(100 * (float) $r->points / (float) $r->max_points, 1),
                'count' => (int) $r->cnt,
            ])
            ->sortBy([['avg_pct', $direction], ['count', 'desc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * For each yes/no question: % Yes among Yes+No answers (NA answers carry no points and are excluded).
     *
     * @return list<array{field_id: string, title: string, yes: int, no: int, pct_yes: float}>
     */
    public function yesNoCompliance(Form $form, DashboardFilters $filters): array
    {
        $yesNoFieldIds = DB::table('form_fields')
            ->join('form_versions', 'form_versions.id', '=', 'form_fields.form_version_id')
            ->where('form_versions.form_id', $form->id)
            ->whereIn('form_fields.type', ['yes_no', 'dropdown', 'multiple_choice'])
            ->distinct()
            ->pluck('form_fields.typeform_field_id')
            ->all();

        if ($yesNoFieldIds === []) {
            return [];
        }

        $rows = $this->base($form->hospital, $filters->withForm($form->id))
            ->join('answers', 'answers.submission_id', '=', 'submissions.id')
            ->whereIn('answers.typeform_field_id', $yesNoFieldIds)
            ->whereNotNull('answers.points')
            ->where('answers.max_points', '>', 0)
            ->selectRaw('answers.typeform_field_id as field_id, SUM(CASE WHEN answers.points >= answers.max_points THEN 1 ELSE 0 END) as yes, SUM(CASE WHEN answers.points = 0 THEN 1 ELSE 0 END) as no')
            ->groupBy('answers.typeform_field_id')
            ->toBase()
            ->get();

        $titles = $this->fieldTitles($form, $rows->pluck('field_id')->all());

        return $rows
            ->map(function ($r) use ($titles) {
                $yes = (int) $r->yes;
                $no = (int) $r->no;

                return [
                    'field_id' => (string) $r->field_id,
                    'title' => $titles[$r->field_id]['title'] ?? (string) $r->field_id,
                    'yes' => $yes,
                    'no' => $no,
                    'pct_yes' => $yes + $no > 0 ? round(100 * $yes / ($yes + $no), 1) : 0.0,
                ];
            })
            ->sortBy(fn ($r) => $titles[$r['field_id']]['order'] ?? 999)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Submission>
     */
    public function recent(?Hospital $hospital, DashboardFilters $filters, int $limit = 10): Collection
    {
        return $this->base($hospital, $filters)
            ->with(['form:id,title,uuid', 'hospital:id,name'])
            ->orderByDesc('submissions.submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{green: int, amber: int, red: int, unscored: int}
     */
    public function distribution(?Hospital $hospital, DashboardFilters $filters): array
    {
        $rows = $this->base($hospital, $filters)->toBase()->selectRaw('rag, COUNT(*) as cnt')->groupBy('rag')->pluck('cnt', 'rag');

        return [
            'green' => (int) ($rows['green'] ?? 0),
            'amber' => (int) ($rows['amber'] ?? 0),
            'red' => (int) ($rows['red'] ?? 0),
            'unscored' => (int) ($rows[''] ?? $rows[null] ?? 0),
        ];
    }

    /**
     * Headline KPIs beyond the average score: likelihood to recommend (NPS-type questions, 0–10),
     * critical items passed (% Yes among Yes/No answers) and number of distinct facilities evaluated.
     *
     * @return array{nps_avg: float|null, nps_count: int, yes_pct: float|null, yes_total: int, facilities: int}
     */
    public function kpis(?Hospital $hospital, DashboardFilters $filters): array
    {
        // NPS-type questions, or any scale question the admin marked as "Likelihood to recommend"; normalised to 0–10.
        $nps = $this->base($hospital, $filters)
            ->join('answers', 'answers.submission_id', '=', 'submissions.id')
            ->join('form_fields', 'form_fields.id', '=', 'answers.form_field_id')
            ->where(fn ($q) => $q->where('form_fields.type', 'nps')->orWhere('form_fields.info_key', 'recommend'))
            ->whereNotNull('answers.value_number')
            ->whereNotNull('form_fields.scale_max')
            ->toBase()
            ->selectRaw('AVG((answers.value_number - COALESCE(form_fields.scale_min, 0)) / NULLIF(form_fields.scale_max - COALESCE(form_fields.scale_min, 0), 0) * 10) as avg, COUNT(*) as cnt')
            ->first();

        $yes = $this->base($hospital, $filters)
            ->join('answers', 'answers.submission_id', '=', 'submissions.id')
            ->join('form_fields', 'form_fields.id', '=', 'answers.form_field_id')
            ->whereIn('form_fields.type', ['yes_no', 'dropdown', 'multiple_choice'])
            ->whereNotNull('answers.points')
            ->where('answers.max_points', '>', 0)
            ->toBase()
            ->selectRaw('SUM(CASE WHEN answers.points >= answers.max_points THEN 1 ELSE 0 END) as yes, COUNT(*) as total')
            ->first();

        $facilities = $this->base($hospital, $filters)
            ->leftJoin('branches', 'branches.id', '=', 'submissions.branch_id')
            ->toBase()
            ->selectRaw('COUNT(DISTINCT '.self::FACILITY_EXPR.') as cnt')
            ->value('cnt');

        $yesTotal = (int) ($yes->total ?? 0);

        return [
            'nps_avg' => $nps !== null && $nps->avg !== null ? round((float) $nps->avg, 1) : null,
            'nps_count' => (int) ($nps->cnt ?? 0),
            'yes_pct' => $yesTotal > 0 ? round(100 * (int) $yes->yes / $yesTotal, 0) : null,
            'yes_total' => $yesTotal,
            'facilities' => (int) $facilities,
        ];
    }

    /**
     * Average score per facility (from the "facility" info field), best and worst.
     *
     * @return array{top: list<array{name: string, avg: float, count: int}>, bottom: list<array{name: string, avg: float, count: int}>, total: int}
     */
    public function facilityRanking(?Hospital $hospital, DashboardFilters $filters, int $limit = 5): array
    {
        $rows = $this->base($hospital, $filters)
            ->leftJoin('branches', 'branches.id', '=', 'submissions.branch_id')
            ->whereNotNull('submissions.overall_pct')
            ->toBase()
            ->selectRaw(self::FACILITY_EXPR.' as facility_name, AVG(submissions.overall_pct) as avg, COUNT(*) as cnt')
            ->groupBy('facility_name')
            ->orderByDesc('avg')
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->facility_name, 'avg' => round((float) $r->avg, 1), 'count' => (int) $r->cnt])
            ->values();

        if ($rows->count() <= $limit * 2) {
            return ['top' => $rows->all(), 'bottom' => [], 'total' => $rows->count()];
        }

        return ['top' => $rows->take($limit)->values()->all(), 'bottom' => $rows->slice(-$limit)->values()->all(), 'total' => $rows->count()];
    }

    /**
     * Heat-map: facilities (rows, most evaluated first) × sections of the selected form, or × forms when no form is selected.
     *
     * @return array{columns: list<string>, rows: list<array{label: string, count: int, cells: array<string, float|null>}>}
     */
    public function heatmap(?Hospital $hospital, DashboardFilters $filters, ?Form $form, int $maxRows = 8): array
    {
        $facilityExpr = self::FACILITY_EXPR;

        $facilities = $this->base($hospital, $filters)
            ->leftJoin('branches', 'branches.id', '=', 'submissions.branch_id')
            ->toBase()
            ->selectRaw("$facilityExpr as facility_name, COUNT(*) as cnt")
            ->groupBy('facility_name')
            ->orderByDesc('cnt')
            ->limit($maxRows)
            ->get();

        if ($facilities->isEmpty()) {
            return ['columns' => [], 'rows' => []];
        }
        $names = $facilities->pluck('facility_name')->map(fn ($n) => (string) $n)->all();

        if ($form !== null) {
            $cells = $this->base($hospital, $filters->withForm($form->id))
                ->leftJoin('branches', 'branches.id', '=', 'submissions.branch_id')
                ->join('submission_section_scores as sss', 'sss.submission_id', '=', 'submissions.id')
                ->whereNotNull('sss.pct')
                ->whereRaw("$facilityExpr IN (".implode(',', array_fill(0, count($names), '?')).')', $names)
                ->toBase()
                ->selectRaw("$facilityExpr as row_name, sss.section_name as col_name, AVG(sss.pct) as avg")
                ->groupBy('row_name', 'col_name')
                ->get();
            $order = $form->currentVersion?->sections()->pluck('display_order', 'name')->all() ?? [];
            $columns = $cells->pluck('col_name')->unique()->sortBy(fn ($c) => $order[$c] ?? 999)->values()->map(fn ($c) => (string) $c)->all();
        } else {
            $cells = $this->base($hospital, $filters)
                ->leftJoin('branches', 'branches.id', '=', 'submissions.branch_id')
                ->join('forms', 'forms.id', '=', 'submissions.form_id')
                ->whereNotNull('submissions.overall_pct')
                ->whereRaw("$facilityExpr IN (".implode(',', array_fill(0, count($names), '?')).')', $names)
                ->toBase()
                ->selectRaw("$facilityExpr as row_name, forms.title as col_name, AVG(submissions.overall_pct) as avg")
                ->groupBy('row_name', 'col_name')
                ->get();
            $columns = $cells->pluck('col_name')->unique()->sort()->values()->map(fn ($c) => (string) $c)->all();
        }

        $lookup = [];
        foreach ($cells as $c) {
            $lookup[(string) $c->row_name][(string) $c->col_name] = round((float) $c->avg, 1);
        }

        $rows = [];
        foreach ($facilities as $f) {
            $cellsForRow = [];
            foreach ($columns as $col) {
                $cellsForRow[$col] = $lookup[(string) $f->facility_name][$col] ?? null;
            }
            $rows[] = ['label' => (string) $f->facility_name, 'count' => (int) $f->cnt, 'cells' => $cellsForRow];
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Per-hospital summary for the admin overview.
     *
     * @return array<int, array{count: int, avg: float|null, previous_avg: float|null}>
     */
    public function perHospital(DashboardFilters $filters): array
    {
        $current = $this->base(null, $filters)->toBase()->selectRaw('hospital_id, AVG(overall_pct) as avg, COUNT(*) as cnt')->groupBy('hospital_id')->get()->keyBy('hospital_id');
        $previous = $this->base(null, $filters->previous())->toBase()->selectRaw('hospital_id, AVG(overall_pct) as avg')->groupBy('hospital_id')->get()->keyBy('hospital_id');

        $out = [];
        foreach ($current->keys()->merge($previous->keys())->unique() as $hospitalId) {
            $out[(int) $hospitalId] = [
                'count' => (int) ($current[$hospitalId]->cnt ?? 0),
                'avg' => isset($current[$hospitalId]) && $current[$hospitalId]->avg !== null ? round((float) $current[$hospitalId]->avg, 1) : null,
                'previous_avg' => isset($previous[$hospitalId]) && $previous[$hospitalId]->avg !== null ? round((float) $previous[$hospitalId]->avg, 1) : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function periods(DashboardFilters $filters, string $bucket): array
    {
        $labels = [];
        if ($bucket === 'month') {
            $cursor = $filters->from->startOfMonth();
            while ($cursor->lessThanOrEqualTo($filters->to)) {
                $labels[] = $cursor->format('Y-m');
                $cursor = $cursor->addMonth();
            }
        } else {
            $cursor = $filters->from->startOfWeek(CarbonImmutable::MONDAY);
            while ($cursor->lessThanOrEqualTo($filters->to)) {
                $labels[] = $cursor->format('o-\WW');
                $cursor = $cursor->addWeek();
            }
        }

        return $labels;
    }

    /**
     * Titles for Typeform field ids, preferring the current version, falling back to any version of the form.
     *
     * @param  list<string>  $fieldIds
     * @return array<string, array{title: string, section: string|null, order: int}>
     */
    private function fieldTitles(Form $form, array $fieldIds): array
    {
        if ($fieldIds === []) {
            return [];
        }

        $rows = DB::table('form_fields')
            ->join('form_versions', 'form_versions.id', '=', 'form_fields.form_version_id')
            ->join('sections', 'sections.id', '=', 'form_fields.section_id')
            ->where('form_versions.form_id', $form->id)
            ->whereIn('form_fields.typeform_field_id', $fieldIds)
            ->orderByDesc('form_versions.version_number')
            ->get(['form_fields.typeform_field_id', 'form_fields.title', 'form_fields.display_order', 'sections.name as section_name']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->typeform_field_id] ??= ['title' => (string) $row->title, 'section' => (string) $row->section_name, 'order' => (int) $row->display_order];
        }

        return $out;
    }
}
