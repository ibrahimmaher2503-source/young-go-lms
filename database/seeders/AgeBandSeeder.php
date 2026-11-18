<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\AgeBand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AgeBandSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(static function (): void {
            if (AgeBand::query()->exists()) {
                return;
            }

            foreach ([
                ['kids', ['ar' => 'الأطفال', 'en' => 'Children'], 0, 9],
                ['teens', ['ar' => 'المراهقون', 'en' => 'Adolescents'], 10, 17],
                ['adults', ['ar' => 'البالغون', 'en' => 'Adults'], 18, null],
            ] as $sort => [$tier, $label, $min, $max]) {
                AgeBand::query()->firstOrCreate(['style_tier' => $tier], compact('label', 'min', 'max', 'sort'));
            }
        }, attempts: 3);
    }
}
