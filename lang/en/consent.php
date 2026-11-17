<?php

declare(strict_types=1);

// T-P9-03: cookie consent. All visitor-facing wording is a DRAFT placeholder pending owner and counsel approval (YG-GAP-PRV-004).
return [
    'title' => 'Cookies',
    'text' => 'With your permission we use Google Analytics and Meta Pixel on public pages to understand visits and measure our ads. Nothing is loaded until you choose.',
    'learn_more' => 'Details and settings',
    'draft_note' => 'Draft wording, pending owner and counsel approval.',
    'accept' => 'Accept',
    'decline' => 'Decline',
    'settings_link' => 'Cookie settings',
    'page_title' => 'Cookie settings',
    'page_intro' => 'Choose whether we may use marketing and analytics cookies on public pages. They are never used in lessons, learner or parent areas, or the lesson assistant. You can change your choice here at any time.',
    'state_none' => 'You have not made a choice yet. No marketing or analytics cookies are used.',
    'state_accepted' => 'You accepted marketing and analytics cookies.',
    'state_declined' => 'You declined marketing and analytics cookies.',
    'none_used' => 'This site does not use marketing or analytics cookies at the moment.',
    'admin' => [
        'nav' => 'Marketing tags',
        'title' => 'Marketing tags',
        'intro' => 'Google Analytics 4 and Meta Pixel load on public pages only after a visitor accepts cookies, and never in lessons, learner or parent areas, or the lesson assistant. Leave a field empty to keep that tag off.',
        'edit' => 'Edit',
        'saved' => 'Marketing tags saved.',
        'none' => 'Not set',
        'denied' => 'You are not allowed to change marketing tags.',
        'failed' => 'The marketing tags could not be saved.',
        'version' => 'Consent wording version: :version',
        'fields' => [
            'ga4_measurement_id' => 'Google Analytics 4 measurement ID',
            'meta_pixel_id' => 'Meta Pixel ID',
        ],
        'ga4_help' => 'Looks like G-XXXXXXXXXX.',
        'pixel_help' => 'Digits only.',
    ],
];
