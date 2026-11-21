<?php

declare(strict_types=1);

// T-P9-09: settings hub, general settings and manual payment payee.
return [
    'edit' => 'Edit',
    'saved' => 'Settings saved.',
    'none' => 'Not set',
    'on' => 'On',
    'off' => 'Off',
    'denied' => 'You are not allowed to change settings.',
    'failed' => 'The settings could not be saved.',
    'market_help' => 'Only active markets are listed.',
    'mail_help' => 'Leave empty to keep the sender from the server mail configuration.',

    'hub' => [
        'nav' => 'Settings',
        'title' => 'Young Go settings',
        'intro' => 'Every platform setting in one place. You only see the settings you are allowed to open.',
        'empty' => 'No settings are available to you.',
        'groups' => [
            'platform' => 'Platform, payments and notifications',
            'compliance' => 'Privacy, safeguarding and marketing',
            'governance' => 'Assistant, roles and audit',
        ],
        'entries' => [
            'general' => 'Site name, contact details, social links, language, market, banner and mail sender.',
            'payee' => 'Where payers send manual transfers.',
            'notifications' => 'Email and in-app notification wording.',
            'marketing' => 'Google Analytics and Meta Pixel IDs.',
            'privacy' => 'How long each kind of data is kept.',
            'safeguarding' => 'Extra people told when a child safeguarding case is raised.',
            'data_requests' => 'Export and deletion requests from users.',
            'ai' => 'Lesson assistant switch, provider, quotas and budgets.',
            'roles' => 'Which permissions each role has.',
            'audit' => 'Who changed what and when.',
        ],
    ],

    'safeguarding' => [
        'nav' => 'Safeguarding recipients',
        'title' => 'Safeguarding recipients',
        'intro' => 'Safeguarding notices cannot be switched off. Besides the roles below, these email addresses are told too. Each address must belong to an active user.',
        'roles_note' => 'Always told: every active user with one of these roles: :roles.',
        'explain' => 'These roles are told automatically and cannot be removed here. Use the extra addresses below to tell other people, such as a designated safeguarding lead who has no admin role.',
        'emails' => 'Extra email addresses',
        'emails_help' => 'Up to :max addresses. Type an address and press Enter.',
        'none' => 'No extra addresses.',
        'error_email' => ':email is not a valid email address.',
        'error_max' => 'You can add at most :max addresses.',
    ],

    'general' => [
        'nav' => 'General settings',
        'title' => 'General settings',
        'intro' => 'Site identity, contact details, social links, defaults, banner and mail sender. Empty fields use the built-in defaults.',
    ],

    'payee' => [
        'nav' => 'Manual payment details',
        'title' => 'Manual payment details',
        'intro' => 'Shown to payers at checkout when they pay by transfer.',
        'help' => 'For example an Instapay handle or bank transfer details. Leave empty to use the server default.',
        'source' => [
            'settings' => 'Using the value saved here.',
            'environment' => 'Using the server default (MANUAL_PAYMENT_PAYEE).',
            'none' => 'Nothing is set; checkout shows generic text.',
        ],
    ],

    'sections' => [
        'identity' => 'Site identity',
        'contact' => 'Contact',
        'social' => 'Social links',
        'defaults' => 'Defaults',
        'banner' => 'Maintenance banner',
        'mail' => 'Mail sender',
    ],

    'locale' => ['ar' => 'Arabic', 'en' => 'English'],
    'market' => ['EG' => 'Egypt'],

    'fields' => [
        'site_name_ar' => 'Site name (Arabic)',
        'site_name_en' => 'Site name (English)',
        'tagline_ar' => 'Tagline (Arabic)',
        'tagline_en' => 'Tagline (English)',
        'contact_email' => 'Contact email',
        'contact_phone' => 'Contact phone',
        'contact_address_ar' => 'Address (Arabic)',
        'contact_address_en' => 'Address (English)',
        'social_facebook' => 'Facebook link',
        'social_instagram' => 'Instagram link',
        'social_youtube' => 'YouTube link',
        'social_tiktok' => 'TikTok link',
        'social_x' => 'X link',
        'social_linkedin' => 'LinkedIn link',
        'default_locale' => 'Default language',
        'default_market' => 'Default market',
        'banner_enabled' => 'Show maintenance banner',
        'banner_text_ar' => 'Banner text (Arabic)',
        'banner_text_en' => 'Banner text (English)',
        'mail_from_name' => 'Sender name',
        'mail_from_address' => 'Sender email',
        'mail_reply_to' => 'Reply-to email',
        'manual_payee' => 'Payee details',
    ],
];
