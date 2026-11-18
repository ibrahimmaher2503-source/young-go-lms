<?php

declare(strict_types=1);

return [
    // O-028 (PO decision 2026-11-18): who is told when a minor's help request or an AI safeguarding flag arrives.
    // Every active user holding one of these roles. Specific people can be added in the AI settings
    // (ai.safeguarding_extra_recipients: comma-separated user ids or emails). The wording is DRAFT pending legal review.
    'recipient_roles' => ['admin', 'support'],
];
