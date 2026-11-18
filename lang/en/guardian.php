<?php

declare(strict_types=1);

return [
    'code_invalid' => 'This link code is invalid or has expired.',
    'already_linked' => 'This learner is already linked to your account.',
    'password_confirmation_required' => 'Confirm your password to continue.',
    'verify_email_first' => 'Verify your email address before creating a child account.',
    'verification' => [
        'title' => 'Guardian verification',
        'intro' => 'Protected features for your children stay off until you confirm a 6-digit code sent to your email.',
        'verified' => 'Verified',
        'not_verified' => 'Not verified',
        'send' => 'Send me a code',
        'resend' => 'Send a new code',
        'code' => 'Verification code',
        'code_help' => 'Six digits. The code works for :minutes minutes.',
        'confirm' => 'Confirm code',
        'done' => 'Your account is verified as a guardian.',
    ],
    'otp' => [
        'verify_email_first' => 'Verify your email address first.',
        'wait' => 'Please wait :seconds seconds before asking for another code.',
        'too_many_sends' => 'Too many codes requested. Try again later.',
        'wrong' => 'That code is not correct.',
        'expired' => 'That code has expired or was not requested. Ask for a new one.',
        'locked' => 'Too many wrong tries. Ask for a new code.',
        'mail_subject' => 'Your guardian verification code',
        'mail_body' => 'Your verification code is :code. It works for :minutes minutes.',
        'mail_ignore' => 'If you did not ask for this code, you can ignore this email.',
    ],
];
