<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions;

/**
 * The compare summary a reviewer reads: what a revision changes against the content it started from.
 * Each row is {scope: course|section|item, type: changed|added|removed|renamed|status|visibility|reordered|moved, label, from, to}.
 */
final class RevisionDiff
{
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return list<array{scope: string, type: string, label: string, from: string|null, to: string|null}>
     */
    public static function between(array $base, array $payload): array
    {
        return [...self::course($base, $payload), ...self::sections($base, $payload), ...self::items($base, $payload)];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     */
    public static function isEmpty(array $base, array $payload): bool
    {
        return self::between($base, $payload) === [];
    }

    public static function same(mixed $a, mixed $b): bool
    {
        return json_encode($a) === json_encode($b);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return list<array{scope: string, type: string, label: string, from: string|null, to: string|null}>
     */
    private static function course(array $base, array $payload): array
    {
        $rows = [];
        foreach (RevisionSnapshot::COURSE_FIELDS as $field) {
            $from = $base['course'][$field] ?? null;
            $to = $payload['course'][$field] ?? null;
            if (! self::same($from, $to)) {
                $rows[] = ['scope' => 'course', 'type' => 'changed', 'label' => (string) __('course_revisions.fields.'.$field), 'from' => self::show($from), 'to' => self::show($to)];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return list<array{scope: string, type: string, label: string, from: string|null, to: string|null}>
     */
    private static function sections(array $base, array $payload): array
    {
        $rows = [];
        $baseSections = RevisionSnapshot::sectionsById($base);
        $kept = [];
        foreach ((array) ($payload['sections'] ?? []) as $section) {
            $id = $section['id'] ?? null;
            if ($id === null) {
                $rows[] = ['scope' => 'section', 'type' => 'added', 'label' => RevisionSnapshot::label($section['title']), 'from' => null, 'to' => null];

                continue;
            }
            $kept[] = (int) $id;
            $old = $baseSections[(int) $id] ?? null;
            if ($old === null) {
                continue;
            }
            if (! self::same($old['title'], $section['title'])) {
                $rows[] = ['scope' => 'section', 'type' => 'renamed', 'label' => RevisionSnapshot::label($old['title']), 'from' => RevisionSnapshot::label($old['title']), 'to' => RevisionSnapshot::label($section['title'])];
            }
            if ((bool) $old['published'] !== (bool) $section['published']) {
                $rows[] = ['scope' => 'section', 'type' => 'visibility', 'label' => RevisionSnapshot::label($section['title']), 'from' => self::flag((bool) $old['published']), 'to' => self::flag((bool) $section['published'])];
            }
        }
        foreach ($baseSections as $id => $old) {
            if (! in_array($id, $kept, true)) {
                $rows[] = ['scope' => 'section', 'type' => 'removed', 'label' => RevisionSnapshot::label($old['title']), 'from' => null, 'to' => null];
            }
        }
        $order = array_values(array_filter(array_keys($baseSections), static fn (int $id): bool => in_array($id, $kept, true)));
        if (! self::same($order, $kept)) {
            $rows[] = ['scope' => 'section', 'type' => 'reordered', 'label' => (string) __('course_revisions.sections_reordered'), 'from' => null, 'to' => null];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return list<array{scope: string, type: string, label: string, from: string|null, to: string|null}>
     */
    private static function items(array $base, array $payload): array
    {
        $rows = [];
        $baseItems = RevisionSnapshot::itemsById($base);
        $kept = [];
        foreach ((array) ($payload['sections'] ?? []) as $section) {
            foreach ((array) $section['items'] as $item) {
                $id = $item['id'] ?? null;
                if ($id === null) {
                    $rows[] = ['scope' => 'item', 'type' => 'added', 'label' => RevisionSnapshot::label($item['title']), 'from' => null, 'to' => (string) __('course_revisions.kind.'.$item['kind'])];

                    continue;
                }
                $kept[] = (int) $id;
                $old = $baseItems[(int) $id] ?? null;
                if ($old === null) {
                    continue;
                }
                $label = RevisionSnapshot::label($old['title']);
                foreach (['title', 'description'] as $field) {
                    if (! self::same($old[$field], $item[$field])) {
                        $rows[] = ['scope' => 'item', 'type' => 'changed', 'label' => $label.' - '.__('course_revisions.fields.'.$field), 'from' => self::show($old[$field]), 'to' => self::show($item[$field])];
                    }
                }
                if ($old['status'] !== $item['status']) {
                    $rows[] = ['scope' => 'item', 'type' => 'status', 'label' => $label, 'from' => (string) __('curriculum.status_'.$old['status']), 'to' => (string) __('curriculum.status_'.$item['status'])];
                }
                if ((int) ($old['section_id'] ?? 0) !== (int) ($section['id'] ?? 0)) {
                    $rows[] = ['scope' => 'item', 'type' => 'moved', 'label' => $label, 'from' => null, 'to' => RevisionSnapshot::label($section['title'])];
                }
            }
        }
        foreach ($baseItems as $id => $old) {
            if (! in_array($id, $kept, true) && $old['kind'] !== 'live_session') {
                $rows[] = ['scope' => 'item', 'type' => 'removed', 'label' => RevisionSnapshot::label($old['title']), 'from' => null, 'to' => null];
            }
        }
        $order = array_values(array_filter(array_keys($baseItems), static fn (int $id): bool => in_array($id, $kept, true)));
        if (! self::same($order, $kept)) {
            $rows[] = ['scope' => 'item', 'type' => 'reordered', 'label' => (string) __('course_revisions.items_reordered'), 'from' => null, 'to' => null];
        }

        return $rows;
    }

    private static function flag(bool $published): string
    {
        return (string) __('curriculum.status_'.($published ? 'published' : 'draft'));
    }

    private static function show(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return (string) trans_choice('course_revisions.rows', count($value), ['count' => count($value)]);
            }
            $value = RevisionSnapshot::label($value);
        }
        $text = trim(strip_tags((string) $value));

        return $text === '' ? '-' : mb_strimwidth($text, 0, 140, '...');
    }
}
