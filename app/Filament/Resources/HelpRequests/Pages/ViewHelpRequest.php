<?php

declare(strict_types=1);

namespace App\Filament\Resources\HelpRequests\Pages;

use App\Domain\AI\Help\Actions\ChangeHelpRequestStatus;
use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Models\AiSafeguardingFlag;
use App\Models\HelpRequest;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * T-P7-04: one help request with its context (question, AI answer, cited pages, video position), the reply thread and the
 * resolve actions. The record comes through the resource query, which only holds what the viewer may see, so everything
 * else is a 404. Safeguarding flags show only to staff who may view assistant conversations (O-028 provisional).
 */
final class ViewHelpRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = HelpRequestResource::class;

    protected string $view = 'filament.help.request';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(HelpRequestAccess::allows($this->actor(), $this->helpRequest()), 404);
    }

    public function getTitle(): string
    {
        return __('help.queue.request').' #'.$this->helpRequest()->getKey();
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $open = fn (): bool => $this->helpRequest()->status->isOpen();

        return [
            Action::make('reply')->label(__('help.actions.reply'))->icon('heroicon-o-chat-bubble-left')->color('primary')->modalHeading(__('help.actions.reply'))
                ->visible($open)
                ->schema([
                    Textarea::make('body')->label(__('help.actions.reply_body'))->rows(5)->maxLength((int) config('ai.help.reply_max_chars', 2000))->required(),
                    Checkbox::make('wait')->label(__('help.actions.wait_for_student')),
                ])
                ->action(function (array $data, Action $action): void {
                    $this->attempt($action, fn () => app(ReplyToHelpRequest::class)($this->actor(), $this->helpRequest(), (string) $data['body'], (bool) ($data['wait'] ?? false)), __('help.actions.replied'));
                }),
            $this->statusAction('start', HelpRequestStatus::InProgress, 'heroicon-o-play', 'info')
                ->visible(fn (): bool => $this->helpRequest()->status === HelpRequestStatus::New),
            $this->statusAction('resolve', HelpRequestStatus::Resolved, 'heroicon-o-check-circle', 'success')->visible($open),
            $this->statusAction('close', HelpRequestStatus::Closed, 'heroicon-o-x-circle', 'gray')
                ->visible(fn (): bool => $open() || $this->helpRequest()->status === HelpRequestStatus::Resolved),
        ];
    }

    private function statusAction(string $name, HelpRequestStatus $to, string $icon, string $color): Action
    {
        return Action::make($name)->label(__('help.actions.'.$name))->icon($icon)->color($color)->requiresConfirmation()->modalHeading(__('help.actions.'.$name))
            ->action(function (Action $action) use ($to, $name): void {
                $this->attempt($action, fn () => app(ChangeHelpRequestStatus::class)($this->actor(), $this->helpRequest(), $to), __('help.actions.done_'.$name));
            });
    }

    /** Runs the change; a refused input is shown to the staff member and keeps the dialog open. */
    private function attempt(Action $action, Closure $change, string $done): void
    {
        try {
            $change();
        } catch (HelpRefusedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
            $action->halt();

            return;
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('help.actions.not_allowed'))->send();
            $action->halt();

            return;
        }

        $this->record = $this->resolveRecord($this->helpRequest()->getKey());
        Notification::make()->success()->title($done)->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $request = $this->helpRequest();
        $request->load(['student', 'course', 'lesson.item', 'instructor', 'messages.author', 'events.actor']);
        $viewer = $this->actor();
        $flags = $viewer->can('ai.conversation.view')
            ? AiSafeguardingFlag::query()->where('conversation_id', $request->conversation_id)->orderBy('id')->get()
            : collect();

        return ['helpRequest' => $request, 'flags' => $flags, 'canSeeFlags' => $viewer->can('ai.conversation.view')];
    }

    private function helpRequest(): HelpRequest
    {
        /** @var HelpRequest $request */
        $request = $this->record;

        return $request;
    }

    private function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
