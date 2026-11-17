<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\AI\Help\Actions\ReplyAsStudent;
use App\Domain\AI\Help\HelpLinks;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\Identity\ActingLearner;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The learner's help request thread. Only the learner whose request it is (the child while a parent acts for them) can open it;
 * anyone else gets a 404. In parent context the thread shows status and the instructor's replies only: no AI question or
 * answer, no reply form (YG-GAP-FAM-006, O-016).
 */
final class HelpRequestController
{
    public function __construct(private readonly ActingLearner $acting, private readonly ReplyAsStudent $reply) {}

    public function show(HelpRequest $helpRequest): View
    {
        $this->own($helpRequest);
        $helpRequest->load(['lesson', 'course', 'messages.author', 'instructor']);
        $private = $this->acting->canViewPrivateLearnerConversations();

        return view('learner.help-request', [
            'helpRequest' => $helpRequest,
            'private' => $private,
            'messages' => $private ? $helpRequest->messages : $helpRequest->messages->where('role', 'instructor')->values(),
            'canReply' => $private && $helpRequest->status->learnerMayReply(),
        ]);
    }

    public function reply(Request $request, HelpRequest $helpRequest): RedirectResponse
    {
        $learner = $this->own($helpRequest);
        abort_unless($this->acting->canViewPrivateLearnerConversations(), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:'.(int) config('ai.help.reply_max_chars', 2000)]]);

        try {
            ($this->reply)($learner, $helpRequest, (string) $data['body']);
        } catch (HelpRefusedException $refused) {
            abort_if($refused->reason === HelpRefusedException::NOT_FOUND, 404);

            return redirect(HelpLinks::learner((int) $helpRequest->getKey()))->withErrors(['body' => $refused->getMessage()])->withInput();
        }

        return redirect(HelpLinks::learner((int) $helpRequest->getKey()))->with('help_notice', __('help.thread.sent'));
    }

    private function own(HelpRequest $helpRequest): User
    {
        $learner = $this->acting->current();
        abort_unless($learner !== null && (int) $helpRequest->student_id === (int) $learner->getKey(), 404);

        return $learner;
    }
}
