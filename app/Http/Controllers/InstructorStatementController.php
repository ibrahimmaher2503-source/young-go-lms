<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\InstructorFinance\StatementPdf;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * T-P8-10: statement PDF download. An instructor gets only their own statements; finance and admin (`ledger.view`) may open any.
 * Everyone else, including another instructor, gets a 404.
 */
final class InstructorStatementController
{
    public function pdf(Request $request, InstructorStatement $statement, StatementPdf $pdf): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAttribute('status') === 'active', 404);
        $own = (int) $statement->instructor_id === (int) $user->getKey() && $user->hasAnyRole(PublicInstructors::ROLES);
        abort_unless($own || $user->can('ledger.view'), 404);

        $lang = $request->query('lang');
        $locale = is_string($lang) && in_array($lang, StatementPdf::LOCALES, true) ? $lang : app()->getLocale();

        try {
            return $pdf->build($statement, $locale)->toResponse($request);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, __('settlements.render_failed'));
        }
    }
}
