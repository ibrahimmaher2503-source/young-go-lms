{{-- Props: child (User). Modal for a linked parent to set a new password for the child; asks for the parent's password first. --}}
@props(['child'])
@php($childName = $child->display_name ?: $child->name)
<x-ui.modal :id="'reset-'.$child->id" :title="__('pages.reset_child_title', ['name' => $childName])" :trigger="__('pages.reset_child')">
    <p class="mb-4 text-muted">{{ __('pages.reset_child_intro') }}</p>
    <x-ui.ajax-form :action="url('/guardian/children/'.$child->id.'/password')" :chain="[url('/confirm-password'), url('/guardian/children/'.$child->id.'/password')]" :message="__('pages.reset_child_done')" reset>
        <x-ui.form-field :for="'reset-child-password-'.$child->id" :label="__('pages.child_new_password')" :help="__('pages.password_help')" data-field>
            <x-ui.input :id="'reset-child-password-'.$child->id" name="child_password" type="password" autocomplete="new-password" :aria-describedby="'reset-child-password-'.$child->id.'-help'" required />
        </x-ui.form-field>
        <x-ui.form-field :for="'reset-child-confirm-'.$child->id" :label="__('pages.password_confirmation')" data-field>
            <x-ui.input :id="'reset-child-confirm-'.$child->id" name="child_password_confirmation" type="password" autocomplete="new-password" required />
        </x-ui.form-field>
        <x-ui.form-field :for="'reset-parent-password-'.$child->id" :label="__('pages.your_password')" data-field>
            <x-ui.input :id="'reset-parent-password-'.$child->id" name="password" type="password" autocomplete="current-password" required />
        </x-ui.form-field>
        <x-ui.button type="submit" class="w-full">{{ __('pages.reset_child_submit') }}</x-ui.button>
    </x-ui.ajax-form>
</x-ui.modal>
