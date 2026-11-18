<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Privacy\Actions\PruneRetainedData;
use App\Domain\Privacy\RetentionClass;
use Illuminate\Console\Command;

final class PrunePrivacyDataCommand extends Command
{
    protected $signature = 'privacy:prune {--class=* : Limit to one or more retention classes}';

    protected $description = 'Delete data past its retention period per class (YG-GAP-PRV-002). Never touches financial, certificate or audit data.';

    public function handle(PruneRetainedData $prune): int
    {
        $only = null;
        $names = array_values(array_filter((array) $this->option('class'), 'is_string'));
        if ($names !== []) {
            $only = [];
            foreach ($names as $name) {
                $class = RetentionClass::tryFrom($name);
                if ($class === null) {
                    $this->error(sprintf('Unknown class "%s". Known: %s', $name, implode(', ', array_map(static fn (RetentionClass $c): string => $c->value, RetentionClass::cases()))));

                    return self::FAILURE;
                }
                $only[] = $class;
            }
        }

        foreach ($prune($only) as $class => $count) {
            $this->line(sprintf('%s: %d', $class, $count));
        }

        return self::SUCCESS;
    }
}
