<x-filament-panels::page>
    @unless ($live)
        <p class="rounded-card border border-border bg-surface p-4 shadow-card" data-revision-not-live>{{ __('course_revisions.not_published') }}</p>
    @endunless

    @if ($open)
        <section class="grid gap-3 rounded-card border border-border bg-surface p-4 shadow-card" data-revision-open="{{ $open->number }}" data-revision-status="{{ $open->status->value }}">
            <h2 class="text-lg font-bold">{{ __('course_revisions.open_title', ['number' => $open->number]) }}</h2>
            <p class="text-sm text-muted">{{ __('course_revisions.state.'.$open->status->value) }} - {{ $open->author?->name }}</p>
            <h3 class="font-semibold">{{ __('course_revisions.compare') }}</h3>
            @include('filament.courses.revision-compare', ['changes' => $changes])
        </section>
    @elseif ($live)
        <p class="rounded-card border border-border bg-surface p-4 shadow-card text-muted" data-revision-none>{{ __('course_revisions.none_open') }}</p>
    @endif

    <section class="rounded-card border border-border bg-surface shadow-card" data-revision-history>
        <h2 class="px-4 pt-4 text-lg font-bold">{{ __('course_revisions.history') }}</h2>
        @if ($history->isEmpty())
            <p class="p-6 text-center text-muted">{{ __('course_revisions.history_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead>
                        <tr class="border-b border-border bg-table-head">
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_number') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_status') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_author') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_reviewer') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_reason') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('course_revisions.col_date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $revision)
                            <tr class="border-b border-border" data-history-row="{{ $revision->number }}" data-history-status="{{ $revision->status->value }}">
                                <td class="px-4 py-3 font-semibold">#{{ $revision->number }}</td>
                                <td class="px-4 py-3">{{ __('course_revisions.state.'.$revision->status->value) }}</td>
                                <td class="px-4 py-3">{{ $revision->author?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $revision->reviewer?->name ?? '-' }}</td>
                                <td class="px-4 py-3" dir="auto">{{ $revision->reason ?? '-' }}</td>
                                <td class="px-4 py-3">{{ ($revision->reviewed_at ?? $revision->submitted_at ?? $revision->created_at)?->isoFormat('LL') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-panels::page>
