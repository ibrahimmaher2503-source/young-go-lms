<?php

declare(strict_types=1);

return [
    'invalid_url' => 'Enter a full https link to a public site.',
    'invalid_path' => 'The file path is not valid.',
    'invalid_type' => 'This file type is not allowed for this source.',
    'file_missing' => 'The file was not found in private storage.',
    'invalid_vimeo' => 'Enter a Vimeo video link.',
    'invalid_youtube' => 'Enter a YouTube video link.',
    'host_not_allowed' => 'This site is not on the approved list for embeds.',
    'invalid_text' => 'Enter the lesson text (up to 20,000 characters).',
    'unknown_provider' => 'Choose a valid media source.',
    't3_paid_blocked' => ':count paid lesson(s) use an open media source. An admin must record an exception before they can be published.',
    'reason_invalid' => 'Explain the exception in :min to :max characters.',
    'no_exception_needed' => 'Only lessons with an open (T3) source need an exception.',
    'tier' => 'Protection',
    'tiers' => [
        'T1' => 'Protected',
        'T2' => 'Restricted',
        'T3' => 'Open',
    ],
    'exception' => 'Exception granted',
    'too_large' => 'The file is empty or larger than the allowed size.',
    'store_failed' => 'The file could not be saved. Try again.',
    'status' => [
        'processing' => 'Processing',
        'ready' => 'Ready',
        'failed' => 'Failed',
    ],
    'pdf_invalid' => 'This is not a readable PDF.',
    'pdf_encrypted' => 'Password-protected PDFs are not accepted. Remove the protection and upload again.',
    'pdf_active_content' => 'This PDF contains scripts or embedded files and cannot be accepted.',
    'pdf_too_large' => 'The PDF is empty or larger than :mb MB.',
    'pdf_too_many_pages' => 'The PDF has more than :max pages.',
    'pdf_failed_version' => 'This version failed checks and cannot be approved.',
];
