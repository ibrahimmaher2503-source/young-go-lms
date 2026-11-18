<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Link between a settlement line and a ledger entry. History: written once, never changed or deleted (a cancelled batch keeps
 * its links; the entries return to `accrued` and may join a later batch).
 *
 * @property int $id
 * @property int $item_id
 * @property int $ledger_entry_id
 */
final class SettlementEntry extends Model
{
    protected $table = 'instructor_settlement_entries';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('A settlement link cannot be changed.');
        });
        self::deleting(static function (): never {
            throw new LogicException('A settlement link cannot be deleted.');
        });
    }
}
