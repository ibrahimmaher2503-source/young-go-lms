<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\InstructorFinance\Actions\GenerateMonthlyStatements;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class GenerateInstructorStatementsCommand extends Command
{
    protected $signature = 'statements:generate {period? : Month as YYYY-MM (default: last month)}';

    protected $description = 'Freeze the monthly instructor statements for a closed month (idempotent; existing statements are kept).';

    public function handle(GenerateMonthlyStatements $generate): int
    {
        $period = $this->argument('period');
        $period = is_string($period) && $period !== '' ? $period : Carbon::now()->subMonthNoOverflow()->format('Y-m');

        try {
            $created = $generate->system($period);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        }

        $this->info('Created '.$created.' statements for '.$period.'.');

        return self::SUCCESS;
    }
}
