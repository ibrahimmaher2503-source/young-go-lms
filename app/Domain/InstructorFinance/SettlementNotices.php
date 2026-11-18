<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\User;
use Throwable;

/**
 * Instructor notices for statements and settlement payouts (matrix events `settlement.statement` and `settlement.status`).
 * Called after the database work commits; a failed notice is reported and never undoes finance work.
 */
final class SettlementNotices
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function statementReady(InstructorStatement $statement): void
    {
        $this->send((int) $statement->instructor_id, new TemplatedNotice('settlement.statement', ['period' => $statement->period], 'settlement-statement:'.$statement->getKey(), url('/instructor/my-statements')));
    }

    public function payoutRecorded(SettlementItem $item, Settlement $settlement): void
    {
        $this->send((int) $item->instructor_id, new TemplatedNotice(
            'settlement.status',
            ['period' => $settlement->reference, 'status' => (string) __('settlements.status_paid')],
            'settlement-paid:'.$item->getKey(),
            url('/instructor/my-statements'),
        ));
    }

    private function send(int $instructorId, TemplatedNotice $notice): void
    {
        try {
            $user = User::query()->find($instructorId);
            if ($user instanceof User) {
                $this->dispatcher->send($user, $notice);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
