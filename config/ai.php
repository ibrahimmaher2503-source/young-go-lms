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

    // The age that separates teens from kids is OPEN too (O-015). While it is null everyone under the adult age gets the
    // simplest style tier.
    'teen_age' => env('AI_TEEN_AGE') === null ? null : (int) env('AI_TEEN_AGE'),

    // Assistant answer limits (AI spec section 4): hard word cap per reply language, 1 to 4 sentences, 300-character question.
    'max_words' => 80,
    'max_words_en' => 70,
    'max_sentences' => 4,
    'question_max_chars' => 300,
    'max_output_tokens' => 220,

    // Request pipeline (AI spec sections 3, 7, 8.2, 9.3).
    'assistant' => [
        'timeout_seconds' => 15,
        'retries' => 1,                 // one automatic retry when the output guard rejects an answer
        'follow_up_turns' => 3,         // follow-up questions after the first one in a thread
        'history_pairs' => 2,           // last Q/A pairs of the same thread sent to the provider
        'retrieval_top_k' => 6,         // chunked mode: chunks per question
        'trim_tolerance' => 1.25,       // an answer up to this much over the word cap is trimmed, above it is retried
        'quote_max_words' => 25,
        'quote_min_chars' => 8,
        'quote_min_similarity' => 88,   // percent, fuzzy match of the quote on a cited page when it is not verbatim
        'leak_ngram_words' => 6,        // a run of this many words copied from the system rules counts as a leak
    ],

    // Input guards, answer cache, quotas and rate limits (AI spec sections 8.1, 8.3, 10; T-P7-02).
    'guards' => [
        'quiz_similarity' => 0.7,        // Dice similarity of the normalised question and an open quiz question that triggers the quiz guard
        'quiz_containment' => 0.8,       // share of a long-enough quiz question found inside the learner's question
        'quiz_min_words' => 4,           // a quiz prompt shorter than this is never matched on containment
        'page_hint_min_overlap' => 0.25, // share of the question's words a lesson page must hold to be named in the quiz-guard hint
    ],
    // Human help (AI spec 12, T-P7-04): SLA before a request counts as overdue (hours), open requests per learner and lesson, reply length.
    'help' => [
        'sla_hours' => 24,
        'max_open_per_lesson' => 3,
        'reply_max_chars' => 2000,
    ],
    'cache' => [
        'ttl_days' => 30,                // AI spec 10.3
    ],
    'rate_limit' => [
        'ask_per_minute' => 8,           // POST assistant messages, per signed-in account
        'status_per_minute' => 60,       // GET assistant status, per signed-in account
    ],

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
