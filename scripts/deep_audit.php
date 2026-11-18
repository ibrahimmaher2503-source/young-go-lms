<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;

require __DIR__.'/telescope_tool.php';

echo "Laravel Environment & Config:\n";
echo '  APP_ENV: '.config('app.env')."\n";
echo '  APP_DEBUG: '.(config('app.debug') ? 'true' : 'false')."\n";
echo '  DB_CONNECTION: '.config('database.default')."\n";
echo '  QUEUE_CONNECTION: '.config('queue.default')."\n";
echo '  CACHE_STORE: '.config('cache.default')."\n";
echo '  MAIL_MAILER: '.config('mail.default')."\n";
echo '  SESSION_DRIVER: '.config('session.driver')."\n";
echo '  TELESCOPE_ENABLED: '.(config('telescope.enabled') ? 'true' : 'false')."\n";

echo "\nTelescope Watchers in config/telescope.php:\n";
$watchers = config('telescope.watchers', []);
foreach ($watchers as $watcher => $cfg) {
    $enabled = is_array($cfg) ? ($cfg['enabled'] ?? true) : (bool) $cfg;
    $short = class_basename($watcher);
    echo "  - {$short}: ".($enabled ? 'ENABLED' : 'DISABLED');
    if (is_array($cfg)) {
        $extra = [];
        if (isset($cfg['slow'])) {
            $extra[] = "slow={$cfg['slow']}ms";
        }
        if (isset($cfg['ignore_paths']) && ! empty($cfg['ignore_paths'])) {
            $extra[] = 'ignore_paths='.implode(',', $cfg['ignore_paths']);
        }
        if (! empty($extra)) {
            echo ' ('.implode(', ', $extra).')';
        }
    }
    echo "\n";
}

echo "\nCurrent Telescope Entries by Type in DB:\n";
$types = DB::table('telescope_entries')
    ->select('type', DB::raw('count(*) as count'))
    ->groupBy('type')
    ->get();
foreach ($types as $t) {
    echo "  - {$t->type}: {$t->count}\n";
}
