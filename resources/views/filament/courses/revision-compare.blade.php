{{-- Compare summary of a revision: what it changes against the live content it started from. $changes comes from RevisionDiff. --}}
@if (count($changes) === 0)
    <p class="text-sm text-muted" data-revision-no-changes>{{ __('course_revisions.no_changes') }}</p>
@else
    <p class="text-sm text-muted" data-revision-change-count>{{ trans_choice('course_revisions.change_count', count($changes), ['count' => count($changes)]) }}</p>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('course_revisions.compare') }}">
        <table class="w-full text-start text-sm" data-revision-compare>
            <thead>
                <tr class="border-b border-border bg-table-head">
                    <th scope="col" class="px-3 py-2 text-start">{{ __('course_revisions.col_scope') }}</th>
                    <th scope="col" class="px-3 py-2 text-start">{{ __('course_revisions.col_change') }}</th>
                    <th scope="col" class="px-3 py-2 text-start">{{ __('course_revisions.col_item') }}</th>
                    <th scope="col" class="px-3 py-2 text-start">{{ __('course_revisions.col_from') }}</th>
                    <th scope="col" class="px-3 py-2 text-start">{{ __('course_revisions.col_to') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($changes as $change)
                    <tr class="border-b border-border" data-change-scope="{{ $change['scope'] }}" data-change-type="{{ $change['type'] }}">
                        <td class="px-3 py-2">{{ __('course_revisions.scope.'.$change['scope']) }}</td>
                        <td class="px-3 py-2 font-semibold">{{ __('course_revisions.type.'.$change['type']) }}</td>
                        <td class="px-3 py-2" dir="auto">{{ $change['label'] }}</td>
                        <td class="px-3 py-2 text-muted" dir="auto">{{ $change['from'] ?? '-' }}</td>
                        <td class="px-3 py-2" dir="auto">{{ $change['to'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
