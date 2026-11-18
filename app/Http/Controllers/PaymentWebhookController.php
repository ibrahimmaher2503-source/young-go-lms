<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * T-P8-03: receives the gateway's server callback and hands it to ProcessPaymentWebhook. Answers 401 for a bad signature
 * (nothing stored) and 200 for every verified callback, whatever was decided, so the gateway stops retrying; the reasons
 * stay in `payment_events` and are never echoed back. A failure inside processing is a 500, so the gateway retries.
 */
final class PaymentWebhookController
{
    private const int MAX_BODY_BYTES = 262144;

    public function __invoke(Request $request, ProcessPaymentWebhook $process): JsonResponse
    {
        if (strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return response()->json(['status' => 'payload_too_large'], 413);
        }
        $payload = $request->json()->all();
        if ($payload === []) {
            return response()->json(['status' => 'invalid_payload'], 400);
        }

        $signature = $request->query('hmac', $request->header('X-Payment-Signature'));

        try {
            $process($payload, is_string($signature) && $signature !== '' ? $signature : null);
        } catch (InvalidPaymentSignature) {
            Log::warning('Payment webhook rejected: invalid signature.', ['ip' => $request->ip()]); // never the body

            return response()->json(['status' => 'invalid_signature'], 401);
        }

        return response()->json(['status' => 'ok']);
    }
}
