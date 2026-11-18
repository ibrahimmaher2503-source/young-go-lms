<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Reports\Actions\ExportReportCsv;
use App\Domain\Reports\ReportFilters;
use App\Domain\Reports\ReportKey;
use App\Domain\Reports\ReportTooLargeException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-P9-06: CSV download of a report. The filters come from the query string and are re-scoped on the server for the signed-in user;
 * a report the user may not read is a 403 and an unknown key a 404. The authorisation, size check and audit run before the response
 * starts; the file is then streamed.
 */
final class ReportExportController
{
    public function __invoke(Request $request, string $report, ExportReportCsv $export): StreamedResponse
    {
        $key = ReportKey::tryFrom($report);
        abort_if($key === null, 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $prepared = $export->prepare($user, $key, ReportFilters::fromInput($request->query()));
        } catch (AuthorizationException) {
            abort(403);
        } catch (ReportTooLargeException $exception) {
            abort(422, __('reports.too_large', ['rows' => number_format($exception->rows), 'cap' => number_format($exception->cap)]));
        }

        return response()->streamDownload(static function () use ($export, $prepared): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            $export->stream($prepared, $out);
            fclose($out);
        }, $prepared->filename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
