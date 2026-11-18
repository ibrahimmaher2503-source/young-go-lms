<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Actions\IssueReceipt;
use App\Domain\Commerce\EReceipt;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\Queries\PaymentHistory;
use App\Domain\Commerce\ReceiptPdf;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * T-P8-06: the signed-in account's own payment history and receipt downloads (sale receipt and refund credit notes). Scoped to the
 * payer account; anyone else, including a child of the payer or another parent, gets a 404.
 */
final class PaymentHistoryController
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('learner.payments', ['orders' => PaymentHistory::for($user)]);
    }

    public function receipt(Request $request, Order $order, IssueReceipt $issue, ReceiptPdf $pdf): Response
    {
        $this->payer($request, $order);
        abort_unless(PaymentHistory::hasReceipt($order), 404);

        return $this->render($request, $issue($order), $pdf);
    }

    public function refundReceipt(Request $request, Order $order, Refund $refund, IssueReceipt $issue, ReceiptPdf $pdf): Response
    {
        $this->payer($request, $order);
        abort_unless((int) $refund->order_id === (int) $order->getKey() && $refund->status === RefundStatus::Executed, 404);

        return $this->render($request, $issue($order, $refund), $pdf);
    }

    private function render(Request $request, EReceipt $receipt, ReceiptPdf $pdf): Response
    {
        $lang = $request->query('lang');
        $locale = is_string($lang) && in_array($lang, ReceiptPdf::LOCALES, true) ? $lang : app()->getLocale();

        try {
            return $pdf->build($receipt, $locale)->toResponse($request);
        } catch (Throwable $exception) {
            report($exception);
            abort(503, __('receipts.render_failed'));
        }
    }

    private function payer(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAttribute('status') === 'active' && (int) $order->payer_id === (int) $user->getKey(), 404);
    }
}
