{{-- Entry button for the lesson assistant (AI spec 15). Render only when the assistant is available; it controls <x-learning.assistant-panel>. --}}
<x-ui.button type="button" variant="secondary" data-ai-open aria-controls="ai-panel" aria-expanded="false"><x-ui.icon name="sparkle" class="text-primary" /> {{ __('ai.title') }}</x-ui.button>
