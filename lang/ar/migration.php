<?php

declare(strict_types=1);

return [
    'mode' => [
        'dry_run_banner' => 'اكتملت المحاكاة التجريبية للترحيل (لم يتم تعديل أي جداول مستهدفة أو جداول تتبع).',
        'apply_banner' => 'اكتمل تنفيذ الترحيل الفعلي بنجاح.',
    ],
    'errors' => [
        'mutually_exclusive_mode' => 'لا يمكن الجمع بين الخيارين --dry-run و --apply معًا.',
    ],
    'status' => [
        'running' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'failed' => 'فشل',
        'dry_run' => 'تجريبي',
    ],
];
