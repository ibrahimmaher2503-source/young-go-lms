<?php

declare(strict_types=1);

namespace App\Domain\Migration\Exceptions;

use RuntimeException;

final class LegacyWriteForbiddenException extends RuntimeException {}
