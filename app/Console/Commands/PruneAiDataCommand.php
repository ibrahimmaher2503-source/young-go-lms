<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AI\Admin\Actions\PruneAiData;
use Illuminate\Console\Command;

final class PruneAiDataCommand extends Command
{
    protected $signature = 'ai:prune';

    protected $description = 'Delete AI messages, usage rows, expired cache rows and old quota counters past their retention (AI spec 18).';

    public function handle(PruneAiData $prune): int
    {
        $report = $prune();
        $this->info(sprintf(
            'Pruned %d messages, %d conversations, %d usage rows, %d cache rows and %d quota counters.',
            $report['messages'], $report['conversations'], $report['usage'], $report['cache'], $report['counters'],
        ));

        return self::SUCCESS;
    }
}
