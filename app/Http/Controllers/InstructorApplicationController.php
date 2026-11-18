<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\ActingLearner;
use App\Domain\Instructors\Actions\SubmitInstructorApplication;
use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-P9-01: public "Teach with Young Go" page and application (YG-GAP-INS-001). Anyone may read the page; applying needs a signed-in,
 * verified adult account (approval attaches a role to that account). Spam: honeypot + per-address throttle (route). The ID
 * document is private: only `instructor.manage` staff can download it, never through a public URL.
 */
final class InstructorApplicationController
{
    private const array DOWNLOAD_HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

    public function show(Request $request, ActingLearner $acting): View
    {
        $user = $request->user();
        $state = 'guest';
        $application = null;

        if ($user instanceof User) {
            $application = InstructorApplication::query()->where('user_id', $user->getKey())->latest('id')->first();
            $state = match (true) {
                $user->hasAnyRole(PublicInstructors::ROLES) => 'instructor',
                $user->getAttribute('email') === null || $acting->isParentContext() => 'ineligible',
                ! $user->hasVerifiedEmail() => 'unverified',
                $application?->status === ApplicationStatus::Pending => 'pending',
                default => 'form',
            };
        }

        return view('instructors.teach', [
            'state' => $state,
            'application' => $application,
            'maxMegabytes' => round(Config::integer('instructor_onboarding.id_document_max_kilobytes') / 1024, 1),
            'maxLinks' => Config::integer('instructor_onboarding.max_portfolio_links'),
        ]);
    }

    public function store(Request $request, SubmitInstructorApplication $submit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // A filled decoy field means a bot: answer like a success and store nothing.
        if (filled($request->input(Config::string('contact.honeypot_field')))) {
            return redirect()->route('instructors.teach')->with('teach_notice', __('instructor_onboarding.submitted'));
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'discipline' => ['required', 'string', 'min:2', 'max:120'],
            'experience_years' => ['nullable', 'integer', 'between:0,70'],
            'bio' => ['required', 'string', 'min:30', 'max:2000'],
            'portfolio_links' => ['required', 'string', 'max:2000'],
            'id_document' => ['required', 'file'],
            'privacy' => ['accepted'],
        ]);

        $links = preg_split('/\R+/', (string) $data['portfolio_links']) ?: [];

        $submit($user, [
            'full_name' => $data['full_name'], 'phone' => $data['phone'] ?? null, 'discipline' => $data['discipline'],
            'experience_years' => $data['experience_years'] ?? null, 'bio' => $data['bio'], 'portfolio_links' => $links,
        ], $request->file('id_document'), app()->getLocale());

        return redirect()->route('instructors.teach')->with('teach_notice', __('instructor_onboarding.submitted'));
    }

    /** Authorised download of the private ID document. Anyone without `instructor.manage` gets a 404. */
    public function document(Request $request, InstructorApplication $application): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && $viewer->getAttribute('status') === 'active' && $viewer->can('instructor.manage'), 404);

        $disk = Storage::disk(Config::string('media.disk'));
        abort_unless($disk->exists($application->id_document_path), 404);
        $extension = pathinfo($application->id_document_path, PATHINFO_EXTENSION);

        return $disk->download($application->id_document_path, 'application-id-'.$application->getKey().'.'.$extension, self::DOWNLOAD_HEADERS + ['Content-Type' => $application->id_document_mime]);
    }
}
