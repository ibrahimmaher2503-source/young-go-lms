{{-- Shared Filament table empty state: icon, sentence-case title and a hint, through the shared x-ui.empty-state component. --}}
@props(['icon', 'title', 'description'])
<x-ui.empty-state :icon="$icon" :title="$title" :description="$description" data-empty />
