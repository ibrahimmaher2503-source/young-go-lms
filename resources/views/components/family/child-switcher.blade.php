{{-- Props: child (User). Modal asking the parent to re-enter their password before acting as the child. --}}
@props(['child', 'success' => null])
@php($childName = $child->display_name ?: $child->name)
<x-ui.modal :id="'switch-'.$child->id" :title="__('pages.switch_title', ['name' => $childName])" :trigger="__('pages.switch_child')" trigger-variant="primary">
    <p class="mb-4 text-muted">{{ __('pages.switch_intro') }}</p>
    <x-ui.ajax-form :action="route('guardian.context.enter', $child)" :chain="[url('/confirm-password'), route('guardian.context.enter', $child)]" :success="$success ?? url('/family')">
        <x-ui.form-field :for="'switch-password-'.$child->id" :label="__('pages.your_password')" data-field>
            <x-ui.input :id="'switch-password-'.$child->id" name="password" type="password" autocomplete="current-password" required />
        </x-ui.form-field>
        <x-ui.button type="submit" class="w-full">{{ __('pages.switch_submit') }}</x-ui.button>
    </x-ui.ajax-form>
</x-ui.modal>
