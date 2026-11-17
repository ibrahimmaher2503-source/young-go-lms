<?php

declare(strict_types=1);

return [
    // Largest single submission file in kilobytes when an assignment does not set its own limit. Working default (100 MB).
    'max_kilobytes' => 102400,

    // Hard ceiling an assignment limit may not exceed (kilobytes).
    'ceiling_kilobytes' => 512000,

    // Files per submission revision.
    'max_files' => 5,

    // Longest written answer (characters).
    'max_text_length' => 20000,

    // Which content types each kind of file accepts. The file content decides, never the client name or type.
    'kinds' => [
        'image' => ['image/png', 'image/jpeg', 'image/webp'],
        'document' => ['application/pdf', 'text/plain'],
        'video' => ['video/mp4', 'video/webm'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg'],
    ],

    'extensions' => [
        'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'text/plain' => 'txt',
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/ogg' => 'ogg',
    ],
];
