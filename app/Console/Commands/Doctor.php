<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use RuntimeException;
use Throwable;

final class Doctor extends Command
{
    public const HEARTBEAT_KEY = 'app:scheduler-heartbeat';

    protected $signature = 'app:doctor';

    protected $description = 'Check application runtime dependencies and configuration';

    public function handle(Migrator $migrator): int
    {
        $local = app()->environment('local', 'testing');
        $queueDriver = config('queue.connections.'.config('queue.default').'.driver');
        $cacheDriver = config('cache.stores.'.config('cache.default').'.driver');
        $redisConnections = [];

        if ($queueDriver === 'redis') {
            $redisConnections[] = (string) config('queue.connections.'.config('queue.default').'.connection', 'default');
        }
        if ($cacheDriver === 'redis') {
            $redisConnections[] = (string) config('cache.stores.'.config('cache.default').'.connection', 'cache');
        }

        $checks = [
            'Database' => static function (): string {
                DB::connection()->getPdo();

                return 'OK';
            },
            'Redis' => static function () use ($local, $redisConnections): string {
                if ($local && $redisConnections === []) {
                    return 'skipped (local profile uses neither Redis queues nor Redis cache)';
                }

                foreach (array_unique($redisConnections ?: ['default']) as $connection) {
                    if (! Redis::connection($connection)->ping()) {
                        throw new RuntimeException;
                    }
                }

                return 'OK';
            },
            'Queue' => static function () use ($local, $queueDriver): string {
                if ($queueDriver === 'sync' && $local) {
                    return 'synchronous (local profile; no worker checked)';
                }
                if ($queueDriver === 'sync' || Queue::size() < 0) {
                    throw new RuntimeException;
                }

                return 'OK (backend reachable)';
            },
            'Scheduler' => static function (): string {
                $heartbeat = Cache::get(self::HEARTBEAT_KEY);
                $current = now()->timestamp;
                if (! is_int($heartbeat) || $heartbeat < $current - 180 || $heartbeat > $current) {
                    throw new RuntimeException('Heartbeat is missing or stale.');
                }

                return 'OK';
            },
            'Storage' => $this->checkStorage(...),
            'Mail' => static function (): string {
                Mail::mailer()->getSymfonyTransport();

                return 'OK (transport configured; delivery untested)';
            },
            'Migrations' => static function () use ($migrator): string {
                if (! $migrator->repositoryExists()) {
                    throw new RuntimeException;
                }
                $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);
                if (array_diff(array_keys($files), $migrator->getRepository()->getRan()) !== []) {
                    throw new RuntimeException;
                }

                return 'OK';
            },
            'Providers' => $this->checkProviders(...),
            'Flags' => static function (): string {
                $features = Feature::for(null)->all();
                if ($features === []) {
                    return 'none registered';
                }

                return implode(', ', array_map(
                    static fn (string $name): string => $name.'='.(empty($features[$name]) ? 'off' : 'on'),
                    array_keys($features),
                ));
            },
        ];

        $status = self::SUCCESS;
        foreach ($checks as $name => $check) {
            try {
                $this->line($name.': '.$check());
            } catch (Throwable) {
                $this->error($name.': FAIL');
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    private function checkStorage(): string
    {
        $disk = Storage::disk('local');
        $path = '.doctor-'.Str::uuid();
        $marker = Str::random();

        try {
            if (! $disk->put($path, $marker) || $disk->get($path) !== $marker) {
                throw new RuntimeException;
            }
        } finally {
            if (! $disk->delete($path)) {
                throw new RuntimeException;
            }
        }

        return 'OK (private disk write/read/delete)';
    }

    private function checkProviders(): string
    {
        $fields = [
            'postmark' => ['key'],
            'resend' => ['key'],
            'ses' => ['key', 'secret', 'region'],
        ];
        $mailer = config('mail.mailers.'.config('mail.default').'.transport');
        if (! is_string($mailer) || ! isset($fields[$mailer])) {
            return 'no credential check for the selected mail transport';
        }

        foreach ($fields[$mailer] as $field) {
            if (blank(config("services.{$mailer}.{$field}"))) {
                throw new RuntimeException;
            }
        }

        return 'OK (selected mail provider configuration present)';
    }
}
