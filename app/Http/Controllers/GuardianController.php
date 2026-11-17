<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Actions\ConfirmGuardianLink;
use App\Domain\Identity\Actions\EnterChildContext;
use App\Domain\Identity\Actions\ExitChildContext;
use App\Domain\Identity\Actions\IssueLinkCode;
use App\Domain\Identity\Actions\RedeemLinkCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class GuardianController
{
    public function issueCode(Request $request, IssueLinkCode $action): JsonResponse
    {
        $code = $action->handle($this->user($request));

        return response()->json(['code' => $code, 'expires_in_hours' => IssueLinkCode::VALID_HOURS], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function redeem(Request $request, RedeemLinkCode $action): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $link = $action->handle($this->user($request), $data['code']);

        return response()->json(['id' => $link->getKey(), 'status' => $link->status], 201);
    }

    public function confirm(Request $request, int $link, ConfirmGuardianLink $action): Response
    {
        $action->handle($this->user($request), $link);

        return response()->noContent();
    }

    public function enterContext(Request $request, int $child, EnterChildContext $action): Response
    {
        $target = User::query()->find($child);
        abort_if($target === null, 404);
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');
        $action->handle($this->user($request), $target, is_numeric($confirmedAt) ? (int) $confirmedAt : null);

        return response()->noContent();
    }

    public function exitContext(Request $request, ExitChildContext $action): Response
    {
        $action->handle($this->user($request));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
