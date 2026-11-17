<?php

declare(strict_types=1);

return [
    // Provider and model are OPEN (O-014). Until the owner approves one, nothing real is configured and the
    // assistant fails loudly instead of answering. Local development and tests use the fake provider.
    'provider' => env('AI_PROVIDER', 'unconfigured'),

    // The age that counts as an adult for the assistant is OPEN (O-015). While this is null nobody is treated as an
    // adult, so every learner needs guardian consent: the safe default. Set it only after the owner approves a number.
    'adult_age' => env('AI_ADULT_AGE') === null ? null : (int) env('AI_ADULT_AGE'),

    // Prompt-injection guard: a lesson text may never contain this closing tag.
    'source_tag' => 'lesson_source',

    // Context strategy threshold in tokens (AI spec section 5): full-context at or below, chunked above.
    'full_context_max_tokens' => 40000,

    // Style tier names the prompt understands.
    'style_tiers' => ['kids', 'teens', 'adults'],

    'max_words' => 80,
];
