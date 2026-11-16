<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Http\Request;

final class InvalidDomainAction
{
    public function handle(Request $request): void {}
}
