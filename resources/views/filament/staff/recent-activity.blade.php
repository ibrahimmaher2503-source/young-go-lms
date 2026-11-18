<div class="yg-activity-list">
    @forelse($activities as $activity)
        @php($actorName = $activity->causer instanceof \App\Models\User ? $activity->causer->display_name : __('staff.system_actor'))
        <div class="yg-activity-row">
            <x-ui.avatar :name="$actorName" class="yg-person-avatar" />
            <div>
                <p><strong>{{ $actorName }}</strong> {{ \Illuminate\Support\Facades\Lang::has('audit-events.'.$activity->event) ? __('audit-events.'.$activity->event) : __('staff.activity_recorded') }}</p>
                <time class="yg-workspace-note" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->locale(app()->getLocale())->diffForHumans() }}</time>
            </div>
        </div>
    @empty
        <p class="yg-workspace-empty">{{ __('staff.no_recent_activity') }}</p>
    @endforelse
</div>
