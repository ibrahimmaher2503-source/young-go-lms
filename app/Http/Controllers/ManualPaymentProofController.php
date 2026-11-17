<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentProofAccess;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Brick\Math\Exception\MathException;
use Brick\Money\Money as BrickMoney;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T-P8-04: the payer's transfer-proof form for one of their own pending orders, and the authorised download of a proof
 * file. Anyone who is not the payer (or finance/admin staff for the file) gets a 404; the file never has a public URL.
 */
final class ManualPaymentProofController
{
    private const array DOWNLOAD_HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

    public function __construct(private readonly SubmitManualPaymentProof $submit) {}

    public function create(Request $request, Order $order): View
    {
        $this->payer($request, $order);

        return view('learner.manual-payment-proof', [
            'order' => $order,
            'proof' => ManualPaymentProof::query()->where('order_id', $order->getKey())->latest('id')->first(),
            'payable' => $order->status === OrderStatus::Pending && $order->total_minor > 0,
            'maxMegabytes' => round(Config::integer('manual_payments.max_kilobytes') / 1024, 1),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $payer = $this->payer($request, $order);
        $data = $request->validate([
            'payer_name' => ['required', 'string', 'min:2', 'max:120'],
            'sender_account' => ['required', 'string', 'min:5', 'max:64'],
            'transfer_reference' => ['required', 'string', 'min:4', 'max:64'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'transferred_at' => ['required', 'date'],
            'proof' => ['required', 'file'],
        ]);

        try {
            $minor = BrickMoney::of((string) $data['amount'], $order->currency)->getMinorAmount()->toInt();
        } catch (MathException) {
            throw ValidationException::withMessages(['amount' => __('manual_payment.amount_invalid')]);
        }

        $this->submit->__invoke($payer, $order, [
            'payer_name' => (string) $data['payer_name'], 'sender_account' => (string) $data['sender_account'],
            'transfer_reference' => (string) $data['transfer_reference'], 'amount_minor' => $minor,
            'transferred_at' => Carbon::parse((string) $data['transferred_at'], (string) Config::get('app.timezone')),
        ], $request->file('proof'));

        return redirect()->route('manual-payments.create', $order)->with('manual_payment_notice', __('manual_payment.submitted'));
    }

    public function file(Request $request, ManualPaymentProof $proof): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && ManualPaymentProofAccess::allows($viewer, $proof), 404);

        $disk = Storage::disk(Config::string('media.disk'));
        abort_unless($disk->exists($proof->file_path), 404);
        $extension = pathinfo($proof->file_path, PATHINFO_EXTENSION);

        return $disk->download($proof->file_path, 'transfer-proof-'.$proof->getKey().'.'.$extension, self::DOWNLOAD_HEADERS + ['Content-Type' => $proof->file_mime]);
    }

    private function payer(Request $request, Order $order): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAttribute('status') === 'active' && (int) $order->payer_id === (int) $user->getKey(), 404);

        return $user;
    }
}
