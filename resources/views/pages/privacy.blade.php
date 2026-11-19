@php
    $tones = ['requested' => 'info', 'approved' => 'warning', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'neutral'];
@endphp
<x-layouts.learner :title="__('privacy.page_title')">
    <h1 class="mb-2 text-3xl font-bold">{{ __('privacy.page_title') }}</h1>
    <p class="mb-6 text-muted">{{ __('privacy.page_intro') }}</p>

    @if(session('privacy_notice'))
        <x-ui.alert tone="success" role="status" class="mb-4">{{ session('privacy_notice') }}</x-ui.alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card :title="__('privacy.new_request')">
            <form method="post" action="{{ route('profile.privacy.store') }}" class="space-y-4" novalidate data-privacy-form>
                @csrf
                @if($children->isNotEmpty())
                    <x-ui.form-field for="subject_id" :label="__('privacy.for_whom')">
                        <x-ui.select id="subject_id" name="subject_id">
                            <option value="{{ $user->id }}">{{ __('privacy.for_me') }}</option>
                            @foreach($children as $child)
                                <option value="{{ $child->id }}" @selected((int) old('subject_id') === (int) $child->id)>{{ $child->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-field>
                @endif
                <x-ui.form-field for="type" :label="__('privacy.request_type')" :error="$errors->first('type')">
                    <x-ui.select id="type" name="type" :invalid="$errors->has('type')">
                        @foreach(\App\Domain\Privacy\DataRequestType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.form-field>
                <x-ui.form-field for="details" :label="__('privacy.details')" :help="__('privacy.details_help')" :error="$errors->first('details')">
                    <x-ui.textarea id="details" name="details" rows="4" maxlength="2000" :invalid="$errors->has('details')">{{ old('details') }}</x-ui.textarea>
                </x-ui.form-field>
                <p class="text-sm text-muted">{{ __('privacy.deletion_warning') }}</p>
                <x-ui.button type="submit" variant="primary">{{ __('privacy.submit') }}</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card :title="__('privacy.your_requests')">
            @if($requests->isEmpty())
                <x-ui.empty-state :title="__('privacy.no_requests')" :description="__('privacy.no_requests_help')" data-empty />
            @else
                <ul class="space-y-3">
                    @foreach($requests as $item)
                        <li class="border-b border-border pb-3" data-request="{{ $item->id }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold">{{ $item->type->label() }} <span class="font-normal text-muted">&middot; {{ $item->subject?->name }}</span></p>
                                <x-ui.badge :tone="$tones[$item->status->value] ?? 'neutral'">{{ $item->status->label() }}</x-ui.badge>
                            </div>
                            <p class="text-sm text-muted">{{ $item->created_at?->locale(app()->getLocale())->isoFormat('LL') }}</p>
                            @if($item->status === \App\Domain\Privacy\DataRequestStatus::Rejected && $item->review_note)
                                <p class="mt-1 text-sm" dir="auto">{{ __('privacy.rejected_reason') }}: {{ $item->review_note }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-3">
                                @if($item->hasDownloadableArchive())
                                    <x-ui.link :href="route('profile.privacy.download', $item->id)" data-download>{{ __('privacy.download') }}</x-ui.link>
                                    <span class="text-sm text-muted">{{ __('privacy.expires', ['date' => $item->archive_expires_at?->locale(app()->getLocale())->isoFormat('LL')]) }}</span>
                                @elseif($item->type === \App\Domain\Privacy\DataRequestType::Export && $item->status === \App\Domain\Privacy\DataRequestStatus::Completed)
                                    <span class="text-sm text-muted">{{ __('privacy.archive_expired') }}</span>
                                @endif
                                @if($item->status === \App\Domain\Privacy\DataRequestStatus::Requested)
                                    <form method="post" action="{{ route('profile.privacy.cancel', $item->id) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary">{{ __('privacy.cancel') }}</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card class="mt-4" :title="__('privacy.what_we_keep')">
        <p class="text-sm text-muted">{{ __('privacy.what_we_keep_body') }}</p>
    </x-ui.card>
</x-layouts.learner>
