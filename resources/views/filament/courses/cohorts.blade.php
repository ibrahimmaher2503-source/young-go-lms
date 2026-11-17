<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('cohorts.note') }}</p>
    @forelse($cohorts as $cohort)
        @php($seated = $cohort->members->where('status', 'seated'))
        @php($waiting = $cohort->members->where('status', 'waitlisted'))
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900" data-cohort="{{ $cohort->id }}">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-white/10">
                <div class="min-w-0">
                    <h2 class="text-lg font-bold break-words">{{ $cohort->name }}</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $cohort->capacity === null ? __('cohorts.unlimited') : $seated->count().' / '.$cohort->capacity }}
                        @if($cohort->capacity !== null && $seated->count() >= $cohort->capacity) · <strong data-cohort-full>{{ __('cohorts.full_label') }}</strong>
                        @elseif($cohort->capacity !== null) · {{ __('cohorts.seats_left', ['count' => $cohort->capacity - $seated->count()]) }}@endif
                        @if($cohort->status === 'closed') · <strong>{{ __('cohorts.closed_label') }}</strong>@endif
                        @if($cohort->starts_on) · {{ $cohort->starts_on->isoFormat('LL') }}@endif
                        @if($cohort->ends_on) – {{ $cohort->ends_on->isoFormat('LL') }}@endif
                    </p>
                    @if($cohort->instructors->isNotEmpty())<p class="text-sm">{{ __('cohorts.instructors') }}: {{ $cohort->instructors->pluck('name')->join('، ') }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="mountAction('editCohort', { cohort: {{ $cohort->id }} })">{{ __('cohorts.edit') }}</button>
                    <button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="toggleStatus({{ $cohort->id }})">{{ $cohort->status === 'open' ? __('cohorts.close') : __('cohorts.reopen') }}</button>
                    @if($waiting->isNotEmpty())
                        <button type="button" class="min-h-11 rounded-lg bg-primary-600 px-3 font-semibold text-white" wire:click="promote({{ $cohort->id }})" data-promote>{{ __('cohorts.promote') }}</button>
                    @endif
                </div>
            </header>
            @if($cohort->members->isEmpty())
                <p class="p-4 text-center text-gray-600 dark:text-gray-300">{{ __('cohorts.no_members') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-start text-sm">
                        <thead><tr class="border-b border-gray-200 dark:border-white/10">
                            <th scope="col" class="px-4 py-3 text-start">{{ __('cohorts.learner') }}</th>
                            <th scope="col" class="px-4 py-3 text-start">{{ __('cohorts.member_status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('cohorts.remove') }}</span></th>
                        </tr></thead>
                        <tbody>
                        @foreach($seated->concat($waiting) as $member)
                            <tr class="border-b border-gray-100 dark:border-white/5" data-member-row data-member-status="{{ $member->status }}">
                                <td class="px-4 py-3 font-semibold">{{ $member->learner->name }}</td>
                                <td class="px-4 py-3">{{ $member->status === 'seated' ? __('cohorts.seated') : __('cohorts.waitlisted').' #'.($loop->iteration - $seated->count()) }}</td>
                                <td class="px-4 py-3"><button type="button" class="min-h-11 rounded-lg border border-gray-300 px-3 font-semibold dark:border-white/20" wire:click="remove({{ $member->id }})" wire:confirm="{{ __('cohorts.remove_confirm') }}">{{ __('cohorts.remove') }}</button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <p class="rounded-xl border border-gray-200 bg-white p-6 text-center dark:border-white/10 dark:bg-gray-900" data-cohorts-empty>{{ __('cohorts.empty') }}</p>
    @endforelse
    <x-filament-actions::modals />
</x-filament-panels::page>
