<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Privacy\Actions\AuthorizeExportDownload;
use App\Domain\Privacy\Actions\CancelDataRequest;
use App\Domain\Privacy\Actions\SubmitDataRequest;
use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestType;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-P9-08: the signed-in account's privacy page. A learner (or a guardian for a verified child) asks for an export, a correction or
 * deletion, follows the request and downloads a finished export. Requests are scoped to the person who made them; anyone else gets a 404.
 */
final class PrivacyController
{
    public function show(Request $request): View
    {
        $user = $this->user($request);

        $children = User::query()->whereIn('id', GuardianChildLink::query()
            ->where('guardian_id', $user->getKey())->where('status', GuardianChildLink::STATUS_VERIFIED)->select('child_id'))
            ->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('pages.privacy', [
            'user' => $user,
            'variant' => $user->hasRole('parent') ? 'parent' : 'student',
            'children' => $children,
            'requests' => DataRequest::query()->with('subject:id,name')->where('requested_by', $user->getKey())->latest('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request, SubmitDataRequest $submit): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'type' => ['required', Rule::enum(DataRequestType::class)],
            'subject_id' => ['nullable', 'integer'],
            'details' => ['nullable', 'string', 'max:2000'],
        ], ['required' => __('privacy.error_type'), 'enum' => __('privacy.error_type')]);

        $subject = $user;
        if (isset($data['subject_id']) && (int) $data['subject_id'] !== (int) $user->getKey()) {
            $subject = User::query()->find((int) $data['subject_id']);
            abort_unless($subject instanceof User, 404);
        }

        // An unauthorized guardian attempt is an AuthorizationException, which the framework answers with 403; show 404 instead.
        try {
            $submit($user, $subject, DataRequestType::from((string) $data['type']), isset($data['details']) ? (string) $data['details'] : null);
        } catch (AuthorizationException) {
            abort(404);
        }

        return redirect()->route('profile.privacy.show')->with('privacy_notice', __('privacy.submitted'));
    }

    public function cancel(Request $request, DataRequest $dataRequest, CancelDataRequest $cancel): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless((int) $dataRequest->requested_by === (int) $user->getKey(), 404);

        $cancel($user, $dataRequest);

        return redirect()->route('profile.privacy.show')->with('privacy_notice', __('privacy.cancelled'));
    }

    public function download(Request $request, DataRequest $dataRequest, AuthorizeExportDownload $authorize): StreamedResponse
    {
        $path = $authorize($this->user($request), $dataRequest);
        abort_if($path === null, 404);

        $disk = Storage::disk(Config::string('media.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, 'my-data-'.$dataRequest->getKey().'.zip', [
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Type' => 'application/zip',
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
