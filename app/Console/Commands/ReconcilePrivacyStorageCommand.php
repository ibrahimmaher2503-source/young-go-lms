<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Privacy\Actions\ReconcileOrphanedUploads;
use Illuminate\Console\Command;

/** Finds orphaned upload files by default (dry run); deletes only when `--apply` is passed. */
final class ReconcilePrivacyStorageCommand extends Command
{
    protected $signature = 'privacy:reconcile-storage
        {--apply : Delete orphaned uploads (dry run by default)}
        {--grace= : Minutes before unreferenced uploads count as orphaned (defaults to config privacy.orphan_grace_minutes)}';

    protected $description = 'Report orphaned upload files on storage disks (dry run by default), or delete them with --apply';

    public function handle(ReconcileOrphanedUploads $reconcile): int
    {
        $apply = (bool) $this->option('apply');
        $graceOption = $this->option('grace');
        if ($graceOption !== null && (! is_numeric($graceOption) || (int) $graceOption < 0)) {
            $this->error('Option --grace must be a non-negative integer.');

            return self::INVALID;
        }

        $grace = $graceOption !== null ? (int) $graceOption : null;
        $counts = $reconcile(null, $apply, $grace);
        $total = array_sum($counts);
        $verb = $apply ? 'Deleted' : 'Found';

        $this->line(sprintf('%s %d orphaned upload(s) (%s):', $verb, $total, $apply ? 'apply' : 'dry-run'));
        foreach ($counts as $category => $count) {
            $this->line(" - {$category}: {$count}");
        }

        return self::SUCCESS;
    }
}
