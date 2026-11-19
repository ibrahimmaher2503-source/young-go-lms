<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

final readonly class RowImportResult
{
    public const string OUTCOME_IMPORTED = 'imported';

    public const string OUTCOME_UPDATED = 'updated';

    public const string OUTCOME_UNCHANGED = 'unchanged';

    public const string OUTCOME_SKIPPED = 'skipped';

    public const string OUTCOME_REJECTED = 'rejected';

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public function __construct(
        public string $outcome,
        public ?string $targetType = null,
        public ?int $targetId = null,
        public ?string $rejectionCode = null,
        public ?string $rejectionReason = null,
        public array $metadata = [],
        public array $exceptions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public static function imported(
        string $targetType,
        int $targetId,
        array $metadata = [],
        array $exceptions = [],
    ): self {
        return new self(self::OUTCOME_IMPORTED, $targetType, $targetId, null, null, $metadata, $exceptions);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public static function updated(
        string $targetType,
        int $targetId,
        array $metadata = [],
        array $exceptions = [],
    ): self {
        return new self(self::OUTCOME_UPDATED, $targetType, $targetId, null, null, $metadata, $exceptions);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public static function unchanged(
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
        array $exceptions = [],
    ): self {
        return new self(self::OUTCOME_UNCHANGED, $targetType, $targetId, null, null, $metadata, $exceptions);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public static function skipped(
        string $code,
        string $reason,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
        array $exceptions = [],
    ): self {
        return new self(self::OUTCOME_SKIPPED, $targetType, $targetId, $code, $reason, $metadata, $exceptions);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ImportExceptionData>  $exceptions
     */
    public static function rejected(
        string $code,
        string $reason,
        array $metadata = [],
        array $exceptions = [],
    ): self {
        return new self(self::OUTCOME_REJECTED, null, null, $code, $reason, $metadata, $exceptions);
    }
}
