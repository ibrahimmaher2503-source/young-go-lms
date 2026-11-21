<?php

declare(strict_types=1);

namespace App\Domain\Support\Queries;

use App\Domain\Audit\AuditLogger;
use App\Domain\Certificates\Certificate;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Entitlements\LearnerAccessRows;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Identity\Models\UserDevice;
use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Privacy\DataRequest;
use App\Domain\Progress\Queries\LearnerCourseRows;
use App\Domain\Support\Learner360Mask;
use App\Domain\Support\ViewAsEligibility;
use App\Models\HelpRequest;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * The Learner 360 record for permitted staff (ED-027, YG-GAP-OPS-001): one read-only view of a learner across modules.
 * Each section is gated by its own permission and contact data is masked by role; a child's contact details, birth year
 * and devices are shown only to people who may see sensitive learner data. Nothing here writes. Access state comes from the
 * Entitlements module; free text a learner or the assistant wrote (help questions, AI answers, message bodies) is never returned.
 */
final class Learner360Record
{
    public function __construct(
        private readonly LearnerAccessRows $access,
        private readonly LearnerCourseRows $progress,
        private readonly ViewAsEligibility $eligibility,
    ) {}

    /** Staff accounts are not learners: their record is never shown here. */
    public function canView(User $viewer, User $learner): bool
    {
        return $viewer->can('learner.record.view') && ! $this->eligibility->isStaff($learner);
    }

    /**
     * @return array<string, mixed>
     */
    public function for(User $viewer, User $learner): array
    {
        $learner->loadMissing('roles');
        $sensitive = $viewer->can('learner.record.sensitive');
        $minor = GuardianAccess::isMinor($learner);
        $rows = config()->integer('support.learner_360.list_rows');
        $locale = app()->getLocale();
        $record = [
            'minor' => $minor,
            'profile' => $this->profile($learner, $sensitive, $minor),
            'guardians' => $this->guardians($learner, $sensitive),
            'access' => $this->accessRows($learner, $sensitive, $rows),
            'orders' => $this->orders($learner, $viewer->can('payment.view'), $rows, $locale),
            'certificates' => $this->certificates($learner, $locale, $rows),
            'help' => $this->help($learner, $rows),
            'notices' => $this->notices($learner, $rows),
            'progress' => $viewer->can('learner.progress.view') ? $this->progressRows($learner) : null,
            'privacy' => $viewer->can('privacy.manage') ? $this->privacy($learner, $rows) : null,
            'devices' => ! $minor || $sensitive ? $this->devices($learner, $rows) : null,
            'audit' => $viewer->can('audit.view') ? $this->audit($learner) : null,
            'view_as' => $viewer->can('audit.view') ? $this->viewAsHistory($learner) : null,
        ];

        $record['sections'] = array_keys(array_filter($record, static fn (mixed $value, string $key): bool => $value !== null && $key !== 'minor', ARRAY_FILTER_USE_BOTH));

        return $record;
    }

    /** @return array<string, mixed> */
    private function profile(User $learner, bool $sensitive, bool $minor): array
    {
        $email = $learner->email;
        $phone = $learner->getAttribute('phone');
        $phone = is_string($phone) ? $phone : null;
        $lastLogin = $learner->getAttribute('last_login_at');

        return [
            'name' => $learner->name,
            'display_name' => $learner->getAttribute('display_name'),
            'username' => $learner->getAttribute('username'),
            // A child's own contact details are not shown at all; the guardian section is the way to reach the family.
            'email' => $minor ? null : ($sensitive ? $email : Learner360Mask::email($email)),
            'phone' => $minor ? null : ($sensitive ? $phone : Learner360Mask::phone($phone)),
            'contact_hidden' => $minor,
            'masked' => ! $sensitive,
            'birth_year' => $sensitive && ! $minor ? $learner->getAttribute('birth_year') : null,
            'locale' => $learner->getAttribute('locale'),
            'timezone' => $learner->getAttribute('timezone'),
            'status' => (string) $learner->getAttribute('status'),
            'joined_at' => $learner->created_at?->isoFormat('LL'),
            'last_login_at' => $lastLogin instanceof DateTimeInterface ? Carbon::instance($lastLogin)->isoFormat('LLL') : null,
        ];
    }

    /** @return list<array{name: string, email: string|null, verified: bool, relation: string}> */
    private function guardians(User $learner, bool $sensitive): array
    {
        $out = [];
        $links = GuardianChildLink::query()->where('child_id', $learner->getKey())->orWhere('guardian_id', $learner->getKey())->with(['guardian', 'child'])->limit(20)->get();

        foreach ($links as $link) {
            $isChildSide = (int) $link->child_id === (int) $learner->getKey();
            $other = $isChildSide ? $link->guardian : $link->child;
            if (! $other instanceof User) {
                continue;
            }

            $out[] = [
                'name' => (string) ($other->getAttribute('display_name') ?: $other->name),
                'email' => $isChildSide ? ($sensitive ? $other->email : Learner360Mask::email($other->email)) : null,
                'verified' => $link->status === GuardianChildLink::STATUS_VERIFIED,
                'relation' => $isChildSide ? 'guardian' : 'child',
            ];
        }

        return $out;
    }

    /**
     * Access rows with a human label for the source reference ("Order YG-ORD-1", "Subscription period 1") instead of
     * the internal "kind:id" token.
     *
     * @return list<array<string, mixed>>
     */
    private function accessRows(User $learner, bool $sensitive, int $limit): array
    {
        return array_map(static function (array $row): array {
            $reference = $row['reference'];
            $row['reference_label'] = null;
            if (is_string($reference) && $reference !== '') {
                [$kind, $ref] = array_pad(explode(':', $reference, 2), 2, null);
                $known = $ref !== null && Lang::has('support.source_refs.'.$kind);
                $row['reference_label'] = __('support.source_refs.'.($known ? $kind : 'other'), ['ref' => $ref ?? $reference]);
            }

            return $row;
        }, $this->access->for($learner, $sensitive, $limit));
    }

    /** Localized kind of a sent notice, from the notice class name (never the English class headline). */
    private static function noticeLabel(string $class): string
    {
        $key = Str::snake(Str::beforeLast($class, 'Notice'));

        return __('support.notice_kinds.'.(Lang::has('support.notice_kinds.'.$key) ? $key : 'other'));
    }

    /** @return list<array<string, mixed>> */
    private function orders(User $learner, bool $payments, int $limit, string $locale): array
    {
        $orders = Order::query()
            ->where(static fn ($query) => $query->where('payer_id', $learner->getKey())->orWhereHas('items', static fn ($items) => $items->where('learner_id', $learner->getKey())))
            ->with(['items' => static fn ($items) => $items->where('learner_id', $learner->getKey()), 'refunds'])
            ->latest('id')->limit($limit)->get();

        $attempts = $payments && $orders->isNotEmpty()
            ? PaymentAttempt::query()->whereIn('order_id', $orders->modelKeys())->orderBy('sequence')->get()->groupBy('order_id')
            : collect();

        return $orders->map(fn (Order $order): array => [
            'reference' => $order->reference,
            'status' => $order->status->value,
            'total' => self::money($order->total_minor, $order->currency),
            'paid_at' => $order->paid_at?->isoFormat('LLL'),
            'paid_by_other' => (int) $order->payer_id !== (int) $learner->getKey(),
            'items' => $order->items->map(static fn ($item): string => (string) ($locale === 'ar' ? ($item->title_ar ?: $item->title_en) : ($item->title_en ?: $item->title_ar)))->filter()->values()->all(),
            // Gateway, attempt status and refunds are finance detail. Provider ids and references are never returned.
            'attempts' => $payments ? $attempts->get($order->getKey(), collect())->map(static fn (PaymentAttempt $attempt): array => [
                'gateway' => $attempt->gateway, 'status' => $attempt->status->value, 'amount' => self::money($attempt->amount_minor, $attempt->currency),
            ])->all() : null,
            'refunds' => $payments ? $order->refunds->map(static fn ($refund): array => [
                'reference' => $refund->reference, 'status' => $refund->status->value, 'amount' => self::money($refund->amount_minor, $refund->currency),
                'at' => ($refund->executed_at ?? $refund->created_at)?->isoFormat('LL'),
            ])->all() : null,
        ])->all();
    }

    /** @return list<array{number: string, course: string, issued_at: string, valid: bool}> */
    private function certificates(User $learner, string $locale, int $limit): array
    {
        return Certificate::query()->where('learner_id', $learner->getKey())->latest('issued_at')->limit($limit)->get()
            ->map(static fn (Certificate $certificate): array => [
                'number' => $certificate->number, 'course' => $certificate->titleIn($locale), 'issued_at' => $certificate->issued_at->isoFormat('LL'), 'valid' => $certificate->isValid(),
            ])->all();
    }

    /** @return list<array{id: int, course: string, status: string, created_at: string, overdue: bool}> */
    private function help(User $learner, int $limit): array
    {
        return HelpRequest::query()->where('student_id', $learner->getKey())->with('course')->latest('id')->limit($limit)->get()
            ->map(static fn (HelpRequest $request): array => [
                'id' => (int) $request->getKey(), 'course' => (string) $request->course?->title, 'status' => $request->status->label(),
                'created_at' => $request->created_at?->isoFormat('LLL') ?? '', 'overdue' => $request->isOverdue(),
            ])->all();
    }

    /** @return list<array{label: string, at: string}> Only the kind and time of each notice, never its content. */
    private function notices(User $learner, int $limit): array
    {
        return NotificationDispatch::query()->where('recipient_id', $learner->getKey())->latest('id')->limit($limit)->get()
            ->map(static fn (NotificationDispatch $dispatch): array => [
                'label' => self::noticeLabel($dispatch->notification),
                'at' => $dispatch->created_at->isoFormat('LLL'),
            ])->all();
    }

    /** @return list<array{course: string, status: string, percent: int, done: int, total: int}> */
    private function progressRows(User $learner): array
    {
        return $this->progress->for($learner, false)->map(static fn (array $row): array => [
            'course' => (string) $row['course']->title, 'status' => $row['status'], 'percent' => $row['percent'], 'done' => $row['done'], 'total' => $row['total'],
        ])->values()->all();
    }

    /** @return list<array{type: string, status: string, created_at: string, completed_at: string|null}> */
    private function privacy(User $learner, int $limit): array
    {
        return DataRequest::query()->where('subject_id', $learner->getKey())->latest('id')->limit($limit)->get()
            ->map(static fn (DataRequest $request): array => [
                'type' => $request->type->label(), 'status' => $request->status->label(),
                'created_at' => $request->created_at?->isoFormat('LL') ?? '', 'completed_at' => $request->completed_at?->isoFormat('LL'),
            ])->all();
    }

    /** @return list<array{label: string, last_seen_at: string|null}> */
    private function devices(User $learner, int $limit): array
    {
        return UserDevice::query()->where('user_id', $learner->getKey())->latest('last_seen_at')->limit($limit)->get()
            ->map(static fn (UserDevice $device): array => ['label' => (string) $device->label, 'last_seen_at' => $device->last_seen_at?->isoFormat('LLL')])->all();
    }

    /** @return list<array{event: string, actor: string, at: string}> */
    private function audit(User $learner): array
    {
        return Activity::query()->where('log_name', AuditLogger::LOG_NAME)
            ->where('subject_type', $learner->getMorphClass())->where('subject_id', $learner->getKey())
            ->with('causer')->latest('id')->limit(config()->integer('support.learner_360.audit_rows'))->get()
            ->map(static fn (Activity $activity): array => [
                'event' => Lang::has('audit-events.'.$activity->event) ? __('audit-events.'.$activity->event) : __('audit.other_event'),
                'actor' => $activity->causer instanceof User ? $activity->causer->name : __('staff.system_actor'),
                'at' => $activity->created_at?->isoFormat('LLL') ?? '',
            ])->all();
    }

    /** @return list<array{staff: string, reason: string, started_at: string, ended_at: string|null, cause: string|null, page_views: int}> */
    private function viewAsHistory(User $learner): array
    {
        return ViewAsSession::query()->where('learner_id', $learner->getKey())->with('staff')->latest('id')->limit(10)->get()
            ->map(static fn (ViewAsSession $session): array => [
                'staff' => (string) $session->staff?->name, 'reason' => $session->reason, 'started_at' => $session->started_at->isoFormat('LLL'),
                'ended_at' => $session->ended_at?->isoFormat('LLL'), 'cause' => $session->end_cause, 'page_views' => $session->page_views,
            ])->all();
    }

    private static function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2).' '.$currency;
    }
}
