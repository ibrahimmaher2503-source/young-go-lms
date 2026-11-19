<?php

declare(strict_types=1);

namespace App\Domain\Demo\Actions;

use App\Domain\Catalog\CourseMedia;
use App\Domain\Demo\DemoAnchors;
use App\Domain\Demo\DemoGuard;
use App\Domain\Demo\DemoRefused;
use App\Domain\Entitlements\Actions\PurgeDemoEntitlements;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-P9-11: removes the demo dataset and nothing else. It starts from the registered roots (`demo_anchors`) and follows
 * the schema's own foreign keys to every row that cannot exist without them, deleting leaf tables first. If any collected
 * row also points at a record that is not demo (a real learner enrolled in a demo course, say) it stops before deleting
 * anything. Audit history is append-only and stays. Access rows go through the Entitlements module.
 */
final class ResetDemoData
{
    /** Curriculum rows are reached through the item morph, not a foreign key. */
    private const array MORPH_TABLES = ['lesson' => 'lessons', 'quiz' => 'quizzes', 'assignment' => 'assignments'];

    private const int CHUNK = 400;

    public function __construct(private readonly PurgeDemoEntitlements $entitlements) {}

    /** @return int rows removed (0 when there is no demo data) */
    public function __invoke(): int
    {
        DemoGuard::assertAllowed();

        $roots = [];
        foreach (['users', 'courses', 'coupons', 'bundles', 'subscription_plans'] as $table) {
            $ids = DemoAnchors::ids($table);
            if ($ids !== []) {
                $roots[$table] = $ids;
            }
        }
        if ($roots === []) {
            DB::table('demo_anchors')->delete();

            return 0;
        }

        $edges = $this->edges();
        $rows = $this->collect($roots, $edges);
        $users = $roots['users'] ?? [];

        $this->assertOnlyDemo($rows, $edges, $roots);
        if ($this->entitlements->strangers($users, $roots['courses'] ?? []) > 0) {
            throw new DemoRefused(__('demo.real_data_blocks_reset', ['table' => PurgeDemoEntitlements::TABLE]));
        }

        // Generated demo cover files leave with the courses (the rows go below; the files would otherwise pile up on every reset).
        $covers = DB::table('courses')->whereIn('id', $roots['courses'] ?? [])->pluck('cover_image')->filter()->all();

        $removed = DB::transaction(function () use ($rows, $edges, $users): int {
            $removed = $this->entitlements->__invoke($users);
            $removed += $this->deleteKeyless($rows, $edges);
            $removed += $this->deletePolymorphic($users);
            $removed += $this->deleteKeyed($rows, $edges);
            DB::table('demo_anchors')->delete();

            return $removed;
        });

        foreach ($covers as $cover) {
            CourseMedia::deleteCover((string) $cover);
        }

        return $removed;
    }

    /** @return list<array{child: string, column: string, parent: string}> */
    private function edges(): array
    {
        $edges = [];
        foreach (Schema::getTableListing() as $table) {
            $table = (string) preg_replace('/^.*\./', '', $table);
            if ($table === PurgeDemoEntitlements::TABLE || $table === 'demo_anchors') {
                continue;
            }
            foreach (Schema::getForeignKeys($table) as $key) {
                $action = strtolower((string) ($key['on_delete'] ?? ''));
                // SET NULL rows survive their parent, so they are not part of it.
                if ($action === 'set null' || count($key['columns']) !== 1) {
                    continue;
                }
                $edges[] = ['child' => $table, 'column' => $key['columns'][0], 'parent' => (string) preg_replace('/^.*\./', '', $key['foreign_table'])];
            }
        }

        return $edges;
    }

    /**
     * @param  array<string, list<int>>  $roots
     * @param  list<array{child: string, column: string, parent: string}>  $edges
     * @return array<string, list<int>>
     */
    private function collect(array $roots, array $edges): array
    {
        $rows = $roots;
        do {
            $grew = false;
            foreach ($edges as $edge) {
                if (empty($rows[$edge['parent']]) || ! $this->keyed($edge['child'])) {
                    continue;
                }
                $found = [];
                foreach (array_chunk($rows[$edge['parent']], self::CHUNK) as $chunk) {
                    foreach (DB::table($edge['child'])->whereIn($edge['column'], $chunk)->pluck('id') as $id) {
                        $found[] = (int) $id;
                    }
                }
                $grew = $this->merge($rows, $edge['child'], $found) || $grew;
            }

            if (! empty($rows['curriculum_items'])) {
                foreach (self::MORPH_TABLES as $type => $table) {
                    $found = [];
                    foreach (array_chunk($rows['curriculum_items'], self::CHUNK) as $chunk) {
                        foreach (DB::table('curriculum_items')->whereIn('id', $chunk)->where('itemable_type', $type)->pluck('itemable_id') as $id) {
                            $found[] = (int) $id;
                        }
                    }
                    $grew = $this->merge($rows, $table, $found) || $grew;
                }
            }
        } while ($grew);

        return array_filter($rows, static fn (array $ids): bool => $ids !== []);
    }

    /**
     * @param  array<string, list<int>>  $rows
     * @param  list<int>  $found
     */
    private function merge(array &$rows, string $table, array $found): bool
    {
        $before = count($rows[$table] ?? []);
        $rows[$table] = array_values(array_unique([...($rows[$table] ?? []), ...$found]));

        return count($rows[$table]) > $before;
    }

    private function keyed(string $table): bool
    {
        return in_array('id', Schema::getColumnListing($table), true);
    }

    /**
     * @param  array<string, list<int>>  $rows
     * @param  list<array{child: string, column: string, parent: string}>  $edges
     * @param  array<string, list<int>>  $roots
     */
    private function assertOnlyDemo(array $rows, array $edges, array $roots): void
    {
        foreach ($edges as $edge) {
            if (! in_array($edge['parent'], ['users', 'courses'], true) || empty($rows[$edge['child']]) || empty($roots[$edge['parent']])) {
                continue;
            }
            foreach (array_chunk($rows[$edge['child']], self::CHUNK) as $chunk) {
                $stranger = DB::table($edge['child'])->whereIn('id', $chunk)->whereNotNull($edge['column'])
                    ->whereNotIn($edge['column'], $roots[$edge['parent']])->exists();
                if ($stranger) {
                    throw new DemoRefused(__('demo.real_data_blocks_reset', ['table' => $edge['child']]));
                }
            }
        }
    }

    /**
     * Link tables without an id column: rows that only exist for a demo parent.
     *
     * @param  array<string, list<int>>  $rows
     * @param  list<array{child: string, column: string, parent: string}>  $edges
     */
    private function deleteKeyless(array $rows, array $edges): int
    {
        $removed = 0;
        foreach ($edges as $edge) {
            if ($this->keyed($edge['child']) || empty($rows[$edge['parent']])) {
                continue;
            }
            foreach (array_chunk($rows[$edge['parent']], self::CHUNK) as $chunk) {
                $removed += DB::table($edge['child'])->whereIn($edge['column'], $chunk)->delete();
            }
        }

        return $removed;
    }

    /**
     * Rows tied to a demo account by a type and id instead of a foreign key.
     *
     * @param  list<int>  $users
     */
    private function deletePolymorphic(array $users): int
    {
        $removed = 0;
        $type = (new User)->getMorphClass();
        $links = [
            [Config::string('permission.table_names.model_has_roles'), 'model_type', 'model_id'],
            [Config::string('permission.table_names.model_has_permissions'), 'model_type', 'model_id'],
            ['notifications', 'notifiable_type', 'notifiable_id'],
        ];
        foreach ($links as [$table, $typeColumn, $idColumn]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_chunk($users, self::CHUNK) as $chunk) {
                $removed += DB::table($table)->where($typeColumn, $type)->whereIn($idColumn, $chunk)->delete();
            }
        }

        return $removed;
    }

    /**
     * @param  array<string, list<int>>  $rows
     * @param  list<array{child: string, column: string, parent: string}>  $edges
     */
    private function deleteKeyed(array $rows, array $edges): int
    {
        $pending = array_keys($rows);
        $removed = 0;

        while ($pending !== []) {
            $ready = array_values(array_filter($pending, static function (string $table) use ($pending, $edges): bool {
                foreach ($edges as $edge) {
                    if ($edge['parent'] === $table && $edge['child'] !== $table && in_array($edge['child'], $pending, true)) {
                        return false;
                    }
                }

                return true;
            }));
            if ($ready === []) {
                throw new DemoRefused('Demo tables form a dependency cycle: '.implode(', ', $pending));
            }

            foreach ($ready as $table) {
                // Self-references (a reversal row points at the entry it reverses) go newest first.
                $ids = $rows[$table];
                rsort($ids);
                foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                    $removed += DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }
            $pending = array_values(array_diff($pending, $ready));
        }

        return $removed;
    }
}
