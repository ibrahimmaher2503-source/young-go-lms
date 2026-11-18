<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Guardians\Actions\ConfirmGuardianOtp;
use App\Domain\Guardians\Actions\SendGuardianOtp;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** O-015: the parent asks for a code and enters it on the family page. Limits live in the actions; the routes are throttled too. */
final class GuardianVerificationController
{
    public function send(Request $request, SendGuardianOtp $action): JsonResponse
    {
        $action->handle($this->user($request));

        return response()->json(['sent' => true])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, ConfirmGuardianOtp $action): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $action->handle($this->user($request), $data['code']);

        return response()->json(['verified' => true])->header('Cache-Control', 'no-store');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
