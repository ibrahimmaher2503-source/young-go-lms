<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Concerns\AuthorizesWithPermissions;

abstract class BasePolicy
{
    use AuthorizesWithPermissions;
}
