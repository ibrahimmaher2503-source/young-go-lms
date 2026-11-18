<?php

declare(strict_types=1);

namespace App\Domain\Demo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** The demo marker registry (`demo_anchors`): root records of the demo dataset. */
final class DemoAnchors
{
    public static function mark(Model $model): void
    {
        DB::table('demo_anchors')->insertOrIgnore([
            'table_name' => $model->getTable(), 'record_id' => $model->getKey(), 'created_at' => now(),
        ]);
    }

    /** @return list<int> */
    public static function ids(string $table): array
    {
        return DB::table('demo_anchors')->where('table_name', $table)->pluck('record_id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public static function exists(): bool
    {
        return DB::table('demo_anchors')->exists();
    }
}
