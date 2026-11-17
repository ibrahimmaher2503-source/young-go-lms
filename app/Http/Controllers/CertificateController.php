<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Certificates\Certificate;
use App\Domain\Certificates\CertificatePdf;
use App\Domain\Identity\GuardianAccess;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Certificate download (the learner, or a parent verified-linked to that learner; everyone else gets a 404, as does a
 * revoked certificate) and the public verification page (safe fields only, throttled, noindex).
 */
final class CertificateController
{
    public function download(Request $request, int $certificate, CertificatePdf $pdf): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $row = Certificate::query()->with('learner')->find($certificate);
        abort_if($row === null || ! $row->isValid(), 404);
        $owner = (int) $row->learner_id === (int) $user->getKey();
        abort_unless($owner || GuardianAccess::parentCanAccessChild($user, $row->learner)->allowed(), 404);

        $lang = $request->query('lang');
        $locale = is_string($lang) && in_array($lang, CertificatePdf::LOCALES, true) ? $lang : app()->getLocale();

        try {
            return $pdf->build($row, $locale)->toResponse($request);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, __('certificates.render_failed'));
        }
    }

    public function verify(string $token): View|Response
    {
        $certificate = Certificate::query()->where('token', $token)->first();
        abort_if($certificate === null, 404);

        return response()->view('certificates.verify', ['certificate' => $certificate])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
