<?php

declare(strict_types=1);

// T-P8-11: تقرير المطابقة اليومية للمدفوعات للقسم المالي.
return [
    'nav' => 'مطابقة المدفوعات',
    'title' => 'مطابقة المدفوعات',
    'report_only' => 'يقارن هذا التقرير مدفوعاتنا ببوابة الدفع كل يوم. يعرض الفروقات فقط ولا يغيّر أي طلب أو صلاحية وصول.',
    'last_run' => 'آخر تشغيل',
    'never_run' => 'لم تُشغَّل المطابقة بعد.',
    'run_date' => 'اليوم المطابَق',
    'transactions' => 'معاملات البوابة',
    'matched' => 'مطابقة',
    'open' => 'فروقات مفتوحة',
    'mismatches' => 'الفروقات',
    'filter' => 'عرض',
    'all' => 'الكل',
    'empty' => 'لا توجد فروقات للعرض.',
    'found' => 'تاريخ الرصد',
    'type' => 'النوع',
    'reason' => 'السبب',
    'reference' => 'مرجع الدفع',
    'order' => 'الطلب',
    'gateway_amount' => 'مبلغ البوابة',
    'local_amount' => 'مبلغنا',
    'state' => 'الحالة',
    'actions' => 'الإجراءات',
    'mark_reviewed' => 'تمت المراجعة',
    'reviewed_done' => 'تم وضع علامة تمت المراجعة.',
    'denied' => 'غير مسموح لك بمراجعة الفروقات.',
    'limit_note' => 'يعرض حتى :limit من أحدث السجلات.',
    'status' => [
        'open' => 'مفتوح',
        'reviewed' => 'تمت المراجعة',
    ],
    'types' => [
        'unmatched_transaction' => 'معاملة غير معروفة لدينا',
        'amount_mismatch' => 'اختلاف المبلغ',
        'status_mismatch' => 'اختلاف حالة الدفع',
        'missing_at_gateway' => 'غير موجودة في البوابة',
        'refunded_at_gateway' => 'مستردة في البوابة',
    ],
    'reasons' => [
        'no_attempt_for_reference' => 'لا توجد محاولة دفع بهذا المرجع.',
        'amount_or_currency_differs' => 'المبلغ أو العملة يختلف عن سجلنا.',
        'paid_at_gateway_not_locally' => 'مدفوعة في البوابة وغير مدفوعة في سجلاتنا.',
        'failed_at_gateway_paid_locally' => 'فشلت في البوابة ومدفوعة في سجلاتنا.',
        'not_in_gateway_listing' => 'مدفوعة في سجلاتنا وغير موجودة في قائمة البوابة.',
        'refunded' => 'تم استردادها في البوابة.',
        'voided' => 'تم إلغاؤها في البوابة.',
    ],
];
