{{-- Props: id, accept (mime list), files (existing draft files: id, name, size), maxFiles, limitKb, disabled. Native multipart field "files[]"; existing files can be ticked in "remove[]". The server validates by content. --}}
@props(['id', 'accept' => '', 'files' => [], 'maxFiles' => 5, 'limitKb' => 102400, 'disabled' => false])
<div class="space-y-3" data-submission-uploader>
    @if(count($files) > 0)
        <ul class="space-y-2" aria-label="{{ __('assignment.draft_files') }}" data-draft-files>
            @foreach($files as $file)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-surface px-3 py-2">
                    <span class="flex min-w-0 items-center gap-2"><x-ui.icon name="file" class="size-5 shrink-0 text-muted" /><span class="break-words font-semibold" dir="auto">{{ $file['name'] }}</span><bdi class="text-sm text-muted">{{ \Illuminate\Support\Number::fileSize((int) $file['size'], 1) }}</bdi></span>
                    <span class="flex flex-wrap items-center gap-2">
                        <x-ui.link :href="route('assignment.file', ['file' => $file['id']])" class="min-h-11 font-semibold">{{ __('assignment.download') }}</x-ui.link>
                        @unless($disabled)
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="remove[]" value="{{ $file['id'] }}" class="size-4"> {{ __('assignment.remove_file') }}</label>
                        @endunless
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
    @unless($disabled)
        <x-ui.file-upload :id="$id" :label="__('assignment.add_files')" :hint="__('assignment.upload_hint', ['count' => $maxFiles, 'size' => \App\Domain\Assignments\AssignmentFiles::megabytes((int) $limitKb)])" name="files[]" multiple :accept="$accept" />
    @endunless
</div>
