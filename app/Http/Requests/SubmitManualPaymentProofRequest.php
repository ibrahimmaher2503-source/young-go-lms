<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Commerce\Order;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * T-P8-04: validates the payer's transfer-proof form. Field names come from `attributes()` (lang keys, AR and EN) so no
 * raw field name reaches a message. The transfer time is typed as d/m/Y H:i (ISO is accepted too) and parsed here.
 */
final class SubmitManualPaymentProofRequest extends FormRequest
{
    private const array FORMATS = ['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d'];

    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('order');

        return $user instanceof User && $order instanceof Order && $user->getAttribute('status') === 'active' && (int) $order->payer_id === (int) $user->getKey();
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }

    /** @return array<string, array<int, ValidationRule|string|\Closure>> */
    public function rules(): array
    {
        return [
            'payer_name' => ['required', 'string', 'min:2', 'max:120'],
            'sender_account' => ['required', 'string', 'min:5', 'max:64'],
            'transfer_reference' => ['required', 'string', 'min:4', 'max:64'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'transferred_at' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (self::parse((string) $value) === null) {
                    $fail(__('manual_payment.date_format_invalid'));
                }
            }],
            'proof' => ['required', 'file'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'payer_name' => __('manual_payment.attr_payer_name'),
            'sender_account' => __('manual_payment.attr_sender_account'),
            'transfer_reference' => __('manual_payment.attr_reference'),
            'amount' => __('manual_payment.attr_amount'),
            'transferred_at' => __('manual_payment.attr_transferred_at'),
            'proof' => __('manual_payment.attr_proof'),
        ];
    }

    public function transferredAt(): Carbon
    {
        return self::parse((string) $this->input('transferred_at')) ?? Carbon::now();
    }

    private static function parse(string $value): ?Carbon
    {
        $value = trim($value);
        $zone = (string) Config::get('app.timezone');
        foreach (self::FORMATS as $format) {
            if (! Carbon::hasFormat($value, $format)) {
                continue;
            }
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $value, $zone);
            } catch (Throwable) {
                continue;
            }

            return $parsed instanceof Carbon && ! str_contains($format, 'H') ? $parsed->setTime(12, 0) : $parsed;
        }

        return null;
    }
}
