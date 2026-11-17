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

    // PDF ingestion (AI spec section 6). Thresholds are starting values to tune against real lesson PDFs.
    'ingestion' => [
        'dispatch_on_upload' => (bool) env('AI_INGEST_ON_UPLOAD', true),
        'min_page_chars' => 20,          // below this a page counts as having no text layer, so OCR is tried
        'page_min_score' => 60,          // a page scoring under this is unreadable
        'document_min_score' => 70,      // average score under this fails the document
        'max_unreadable_ratio' => 0.25,  // more unreadable pages than this fails the document
        'repeat_ratio' => 0.6,           // a line on this share of pages (3+ pages) is a header or footer
        'key_terms_max' => 8,
        'key_terms_min' => 3,
        'chunk_tokens' => 800,           // chunk size in chunked mode
    ],
];
