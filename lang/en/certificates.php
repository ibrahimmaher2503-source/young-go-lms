<?php

declare(strict_types=1);

return [
    'reason_required' => 'Enter a reason of 3 to 500 characters.',
    'not_active' => 'This certificate is no longer valid.',
    'render_failed' => 'We could not prepare the certificate right now. Please try again later.',
    'download' => 'Download certificate (PDF)',
    'download_language' => 'Language: :language',
    'verify_link' => 'Verification page',
    'languages' => ['ar' => 'العربية', 'en' => 'English'],
    'issued_on' => 'Completed :date',
    'notice' => [
        'title' => 'Your certificate is ready',
        'body' => 'You completed ":title". Your certificate is ready to download.',
    ],
    'verify' => [
        'title' => 'Certificate verification',
        'valid' => 'Valid certificate',
        'revoked' => 'This certificate has been revoked',
        'revoked_note' => 'It is no longer valid.',
        'name' => 'Awarded to',
        'course' => 'Course',
        'date' => 'Completion date',
        'number' => 'Certificate number',
    ],
    'pdf' => [
        'heading' => 'Certificate of Completion',
        'awarded_to' => 'This certificate is proudly awarded to',
        'for_completing' => 'for completing the course',
        'completed_on' => 'Completed on :date',
        'number' => 'Certificate number',
        'verify_at' => 'Verify this certificate at',
        'qr_alt' => 'QR code to the verification page',
        'issuer_placeholder' => 'Issued by Young Go',
    ],
    'staff' => [
        'column' => 'Certificate',
        'none' => 'Not issued',
        'valid' => 'Valid',
        'revoked' => 'Revoked',
        'revoke' => 'Revoke certificate',
        'revoke_confirm' => 'The certificate stops being valid and its verification page shows it as revoked. The completion stays.',
        'revoked_done' => 'Certificate revoked.',
        'reissue' => 'Issue new certificate',
        'reissue_confirm' => 'A new certificate with a new number replaces the current one, which is revoked.',
        'reissued_done' => 'New certificate issued.',
        'reason' => 'Reason',
    ],
];
