# Bugs Found During Test Implementation

## T-P7-06: Admin controls, readiness, usage reports, insights and resilience

### BUG-P7-06-01: Invalid ARIA Attributes on `role="switch"` in Assistant Availability View

- **Location**: `resources/views/filament/ai/availability.blade.php` (lines 29 and 53)
- **Severity**: Accessibility (Axe Critical: `aria-allowed-attr` & `aria-required-attr`)
- **Found by**: `tests/Browser/AssistantAdminBrowserTest.php` (`verifies accessibility for assistant availability page`)
- **Description**:
  The course and lesson toggle buttons use `role="switch"` combined with `aria-pressed="{{ ... ? 'true' : 'false' }}"`:
  ```html
  <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $course->ai_enabled ? 'true' : 'false' }}" ...>
  <button type="button" class="yg-ai-switch" role="switch" aria-pressed="{{ $row['enabled'] ? 'true' : 'false' }}" ...>
  ```
  Under W3C WAI-ARIA 1.2 specifications:
  1. The `switch` role requires the `aria-checked` attribute (`true` or `false`) to convey its binary state.
  2. `aria-pressed` is not a permitted attribute on `role="switch"` (it is reserved for toggle buttons without a role).
  Axe flags this as:
  - `[critical] Elements must only use supported ARIA attributes (aria-allowed-attr)`
  - `[critical] Required ARIA attributes must be provided (aria-required-attr): Required ARIA attribute not present: aria-checked`

- **Remediation**:
  In `resources/views/filament/ai/availability.blade.php`, change `aria-pressed` to `aria-checked`:
  ```blade
  {{-- Line 29 --}}
  <button type="button" class="yg-ai-switch" role="switch" aria-checked="{{ $course->ai_enabled ? 'true' : 'false' }}" wire:click="toggleCourse" wire:loading.attr="disabled" data-course-switch="{{ $course->ai_enabled ? 'on' : 'off' }}">

  {{-- Line 53 --}}
  <button type="button" class="yg-ai-switch" role="switch" aria-checked="{{ $row['enabled'] ? 'true' : 'false' }}" aria-label="{{ $row['title'] }}" wire:click="toggleLesson({{ $row['item_id'] }})" wire:loading.attr="disabled" data-lesson-switch="{{ $row['enabled'] ? 'on' : 'off' }}">
  ```
- **Test Status**:
  Test `tests/Browser/AssistantAdminBrowserTest.php > it('verifies accessibility for assistant availability page')` is marked `->skip('BUG: resources/views/filament/ai/availability.blade.php uses aria-pressed instead of aria-checked on role="switch", violating aria-allowed-attr and aria-required-attr')` pursuant to test task instructions.
