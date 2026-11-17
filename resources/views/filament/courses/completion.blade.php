<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('completion.note') }}</p>

    <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900" data-completion-rules>
        <h2 class="text-lg font-bold">{{ __('completion.rules') }}</h2>
        <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('completion.criteria.lessons') }}</dt><dd class="font-semibold" data-rule="lessons">{{ $rules->lesson_percent === null ? __('completion.rule_off') : $rules->lesson_percent.'%' }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('completion.criteria.quizzes') }}</dt><dd class="font-semibold" data-rule="quizzes">{{ $rules->require_quizzes ? __('completion.rule_on') : __('completion.rule_off') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('completion.criteria.assignments') }}</dt><dd class="font-semibold" data-rule="assignments">{{ $rules->require_assignments ? __('completion.rule_on') : __('completion.rule_off') }}</dd></div>
            <div><dt class="text-sm text-gray-600 dark:text-gray-300">{{ __('completion.criteria.attendance') }}</dt><dd class="font-semibold" data-rule="attendance">{{ $rules->attendance_percent === null ? __('completion.rule_off') : $rules->attendance_percent.'%' }}</dd></div>
        </dl>
        <p class="mt-3 text-sm" data-rule="manual">{{ __('completion.manual_approval') }}: <strong>{{ $rules->manual_approval ? __('completion.rule_on') : __('completion.rule_off') }}</strong></p>
        @unless($rules->hasCriteria())<p class="mt-2 text-sm" data-no-rules>{{ __('completion.no_rules') }}</p>@endunless
    </section>

    <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900" data-completion-learners>
        @if($learners->isEmpty())
            <p class="p-6 text-center text-gray-600 dark:text-gray-300">{{ __('completion.learners_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead><tr class="border-b border-gray-200 dark:border-white/10">
                        <th scope="col" class="px-4 py-3 text-start">{{ __('completion.learner') }}</th>
                        <th scope="col" class="px-4 py-3 text-start">{{ __('completion.status') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('completion.approve') }}</span></th>
                    </tr></thead>
                    <tbody>
                    @foreach($learners as $learner)
                        @php($completion = $done->get($learner->id))
                        @php($report = $reports[$learner->id] ?? null)
                        <tr class="border-b border-gray-100 dark:border-white/5" data-completion-row="{{ $learner->id }}">
                            <td class="px-4 py-3 font-semibold">{{ $learner->name }}</td>
                            <td class="px-4 py-3">
                                @if($completion)
                                    <strong data-completion-state="completed">{{ $completion->source === 'manual' ? __('completion.completed_manual', ['date' => $completion->completed_at->isoFormat('LL')]) : __('completion.completed_auto', ['date' => $completion->completed_at->isoFormat('LL')]) }}</strong>
                                @else
                                    <span data-completion-state="{{ $report?->eligible() ? 'ready' : 'in_progress' }}">{{ $report?->eligible() ? __('completion.ready') : __('completion.in_progress') }}</span>
                                    @if($report)
                                        <ul class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                            @foreach($report->criteria as $key => $criterion)
                                                <li>{{ __('completion.criteria.'.$key) }}: {{ $criterion['have'] }}{{ in_array($key, ['lessons', 'attendance'], true) ? '%' : '' }} / {{ $criterion['need'] }}{{ in_array($key, ['lessons', 'attendance'], true) ? '%' : '' }} · {{ $criterion['met'] ? __('completion.met') : __('completion.not_met') }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @unless($completion)
                                    <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="mountAction('approve', { learner: {{ $learner->id }} })" data-approve>{{ __('completion.approve') }}</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $learners->links() }}</div>
        @endif
    </section>
</x-filament-panels::page>
