<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900" data-course-learners>
        <p class="p-4 text-sm text-gray-600 dark:text-gray-300">{{ __('courses.learners_note') }}</p>
        @if($learners->isEmpty())
            <p class="p-6 text-center text-gray-600 dark:text-gray-300" data-learners-empty>{{ __('courses.learners_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-start text-sm">
                    <thead><tr class="border-y border-gray-200 dark:border-white/10">
                        <th scope="col" class="px-4 py-3 text-start">{{ __('courses.learner') }}</th>
                        <th scope="col" class="px-4 py-3 text-start">{{ __('courses.access') }}</th>
                        <th scope="col" class="px-4 py-3 text-start">{{ __('courses.enrolled_at') }}</th>
                        <th scope="col" class="px-4 py-3 text-start">{{ __('courses.progress') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('courses.details') }}</span></th>
                    </tr></thead>
                    <tbody>
                    @foreach($learners as $learner)
                        <tr class="border-b border-gray-100 dark:border-white/5" data-learner-row>
                            <td class="px-4 py-3 font-semibold">{{ $learner->name }}</td>
                            <td class="px-4 py-3">{{ $learner->access_status === 'active' ? __('courses.access_active') : __('courses.access_ended') }}</td>
                            <td class="px-4 py-3">{{ $learner->enrolled_at ? \Illuminate\Support\Carbon::parse($learner->enrolled_at)->isoFormat('LL') : '' }}</td>
                            <td class="px-4 py-3" data-learner-percent>{{ (int) ($percent[$learner->id] ?? 0) }}%</td>
                            <td class="px-4 py-3"><button type="button" wire:click="toggle({{ $learner->id }})" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" aria-expanded="{{ $openLearner === $learner->id ? 'true' : 'false' }}">{{ __('courses.details') }}</button></td>
                        </tr>
                        @if($openLearner === $learner->id)
                            <tr data-learner-detail><td colspan="5" class="bg-gray-50 px-4 py-3 dark:bg-white/5">
                                <ul class="space-y-1">
                                    @foreach($detail as $row)
                                        <li class="flex flex-wrap justify-between gap-2"><span>{{ $row['title'] }}</span><span>{{ __('courses.lesson_status.'.$row['status']) }} · {{ $row['percent'] }}%@if($row['last_at']) · {{ $row['last_at'] }}@endif</span></li>
                                    @endforeach
                                </ul>
                            </td></tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $learners->links() }}</div>
        @endif
    </section>
</x-filament-panels::page>
