<div class="yg-role-breakdown">
    @forelse($roles as $role)
        <div class="yg-role-row" data-workspace-role="{{ $role->name }}">
            <span>{{ \Illuminate\Support\Facades\Lang::has('roles.'.$role->name) ? __('roles.'.$role->name) : $role->name }}</span>
            <div class="yg-role-measure"><div class="yg-role-meter" role="meter" aria-valuemin="0" aria-valuemax="{{ max(1, $total) }}" aria-valuenow="{{ $role->users_count }}" aria-label="{{ \Illuminate\Support\Facades\Lang::has('roles.'.$role->name) ? __('roles.'.$role->name) : $role->name }}"><span style="--yg-meter-value: {{ min(100, round($role->users_count / max(1, $total) * 100)) }}%"></span></div><span class="yg-role-percentage">{{ min(100, round($role->users_count / max(1, $total) * 100)) }}%</span></div>
            <span class="yg-role-count">{{ $role->users_count }}</span>
        </div>
    @empty
        <p class="yg-workspace-empty">{{ __('staff.no_roles_assigned') }}</p>
    @endforelse
    <p class="fi-sr-only">{{ __('staff.roles_count_note') }}</p>
</div>
