<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\EvaluationCsvExporter;
use App\Domain\Dashboard\EvaluationListQuery;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationExportController extends Controller
{
    public function __invoke(Request $request, EvaluationCsvExporter $exporter): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $hospital = null;
        if ($user->isSuperAdmin()) {
            if ($request->filled('hospital')) {
                $hospital = Hospital::query()->where('uuid', (string) $request->query('hospital'))->firstOrFail();
            }
        } else {
            $hospital = $user->hospital;
            abort_unless($hospital instanceof Hospital, 404);
        }

        $form = null;
        if ($request->filled('form')) {
            $form = Form::withTrashed()->where('uuid', (string) $request->query('form'))->first();
            if ($form !== null && $hospital !== null && $form->hospital_id !== $hospital->id) {
                $form = null; // never leak another hospital's form; the list simply becomes empty
            }
            abort_if($form === null, 404);
        }

        $filters = DashboardFilters::fromRequest(
            (string) $request->query('period', '90d'),
            $request->query('from'),
            $request->query('to'),
            $form?->id,
            $request->filled('branch') ? (int) $request->query('branch') : null,
        );

        $query = (new EvaluationListQuery($user))->build(
            $hospital,
            $filters,
            $request->query('rag'),
            (string) $request->query('search', ''),
            $request->query('status'),
            (string) $request->query('sort', 'date'),
            (string) $request->query('direction', 'desc'),
        );

        $filename = 'evaluations-'.($hospital->slug ?? 'all').'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($exporter, $query, $form): void {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
            foreach ($exporter->rows($query, $form) as $line) {
                echo $line;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
