<?php

declare(strict_types=1);

return [

    'column_manager' => [
        'heading' => 'الأعمدة',

        'position' => 'الموضع :position من :count.',

        'position_in_group' => 'الموضع :position من :count في :group.',

        'reordered' => 'تم نقل :column إلى الموضع :position من :count.',

        'reordered_deferred' => 'تم نقل :column إلى الموضع :position من :count. طبّق الأعمدة لحفظ التغييرات.',

        'reordered_in_group' => 'تم نقل :column إلى الموضع :position من :count في :group.',

        'reordered_in_group_deferred' => 'تم نقل :column إلى الموضع :position من :count في :group. طبّق الأعمدة لحفظ التغييرات.',

        'actions' => [
            'apply' => [
                'label' => 'تطبيق الأعمدة',
            ],

            'move_up' => [
                'label' => 'تحريك :column لأعلى',
            ],

            'move_down' => [
                'label' => 'تحريك :column لأسفل',
            ],

            'reset' => [
                'label' => 'إعادة تعيين',
            ],

            'reorder' => [
                'label' => 'إعادة ترتيب العمود',
            ],
        ],
    ],

    'columns' => [
        'actions' => [
            'label' => 'إجراء | إجراءات',
        ],

        'editable' => [
            'saving' => 'جارٍ حفظ التغيير...',
            'saved' => 'تم حفظ التغيير.',
            'failure' => 'تعذّر تأكيد الحفظ. حدّث الصفحة قبل المحاولة مجدداً.',
            'unavailable' => 'لم يعد بالإمكان تعديل هذا العنصر.',
        ],

        'select' => [
            'loading_message' => 'جارٍ التحميل...',

            'no_options_message' => 'لا توجد خيارات متاحة.',

            'no_search_results_message' => 'لا توجد خيارات مطابقة لبحثك.',

            'placeholder' => 'اختر',

            'searching_message' => 'جارٍ البحث...',

            'search_prompt' => 'ابدأ الكتابة للبحث...',
        ],

        'text' => [
            'actions' => [
                'collapse_list' => '{1} عرض أقل بعنصر واحد|{2} عرض أقل بعنصرين|[3,10] عرض أقل بـ :count عناصر|[11,*] عرض أقل بـ :count عنصراً',

                'expand_list' => '{1} عرض عنصر إضافي واحد|{2} عرض عنصرين إضافيين|[3,10] عرض :count عناصر إضافية|[11,*] عرض :count عنصراً إضافياً',
            ],

            'more_list_items' => '{1} وعنصر آخر|{2} وعنصرين آخرين|[3,10] و:count عناصر أخرى|[11,*] و:count عنصراً آخر',
        ],

        'icon' => [
            'boolean' => [
                'true' => 'نعم',

                'false' => 'لا',
            ],
        ],
    ],

    'fields' => [
        'bulk_select_page' => [
            'label' => 'تحديد/إلغاء تحديد كافة العناصر للإجراءات الجماعية.',
        ],

        'bulk_select_record' => [
            'label' => 'تحديد/إلغاء تحديد العنصر :key للإجراءات الجماعية.',
        ],

        'bulk_select_group' => [
            'label' => 'تحديد/إلغاء تحديد المجموعة :title للإجراءات الجماعية.',
        ],

        'search' => [
            'label' => 'بحث',

            'placeholder' => 'بحث',

            'indicator' => 'بحث',
        ],
    ],

    'summary' => [
        'heading' => 'الملخص',

        'subheadings' => [
            'all' => 'كافة :label',

            'group' => 'ملخص :group',

            'page' => 'هذه الصفحة',
        ],

        'summarizers' => [
            'average' => [
                'label' => 'المتوسط',
            ],

            'count' => [
                'label' => 'العدد',
            ],

            'sum' => [
                'label' => 'المجموع',
            ],
        ],
    ],

    'actions' => [
        'disable_reordering' => [
            'label' => 'إنهاء إعادة ترتيب السجلات',
        ],

        'enable_reordering' => [
            'label' => 'إعادة ترتيب السجلات',
        ],

        'filter' => [
            'label' => 'تصفية',
        ],

        'group' => [
            'label' => 'مجموعة',
        ],

        'open_bulk_actions' => [
            'label' => 'الإجراءات',
        ],

        'column_manager' => [
            'label' => 'تبديل الأعمدة',
        ],

        'reorder_record' => [
            'label' => 'إعادة ترتيب العنصر :key',
        ],

        'move_record_up' => [
            'label' => 'تحريك :label لأعلى',
        ],

        'move_record_down' => [
            'label' => 'تحريك :label لأسفل',
        ],

        'toggle_record_content' => [
            'label' => 'توسيع/طي العنصر :key',
        ],
    ],

    'empty' => [
        'heading' => 'لا توجد بيانات في :model',

        'description' => 'قم بإضافة :model للبدء.',
    ],

    'filters' => [
        'actions' => [
            'apply' => [
                'label' => 'تطبيق التصفيات',
            ],

            'remove' => [
                'label' => 'إلغاء التصفيات',
            ],

            'remove_all' => [
                'label' => 'إلغاء كافة التصفيات',

                'tooltip' => 'إلغاء كافة التصفيات',
            ],

            'reset' => [
                'label' => 'إعادة ضبط التصفيات',
            ],
        ],

        'heading' => 'التصفيات',

        'indicator' => 'التصفيات النشطة',

        'multi_select' => [
            'placeholder' => 'الكل',
        ],

        'select' => [
            'placeholder' => 'الكل',

            'relationship' => [
                'empty_option_label' => 'لا يوجد اختيار',
            ],
        ],

        'trashed' => [
            'label' => 'السجلات المحذوفة',

            'only_trashed' => 'السجلات المحذوفة فقط',

            'with_trashed' => 'مع السجلات المحذوفة',

            'without_trashed' => 'بدون السجلات المحذوفة',
        ],
    ],

    'grouping' => [
        'fields' => [
            'group' => [
                'label' => 'تجميع حسب',
            ],

            'direction' => [
                'label' => 'اتجاه التجميع',

                'options' => [
                    'asc' => 'تصاعدي',

                    'desc' => 'تنازلي',
                ],
            ],
        ],
    ],

    'reorder_indicator' => 'قم بسحب وإسقاط السجلات بالترتيب.',

    'reordering' => [
        'position' => ':label، الموضع :position من :count.',
        'moved' => 'تم نقل :label إلى الموضع :position من :count.',
    ],

    'selection_indicator' => [
        'selected_count' => '{1} تم تحديد سجل واحد|{2} تم تحديد سجلين|[3,10] تم تحديد :count سجلات|[11,*] تم تحديد :count سجلاً',

        'actions' => [
            'select_all' => [
                'label' => 'تحديد جميع السجلات البالغ عددها :count',
            ],

            'deselect_all' => [
                'label' => 'إلغاء تحديد الكل',
            ],
        ],
    ],

    'sorting' => [
        'fields' => [
            'column' => [
                'label' => 'ترتيب حسب',
            ],

            'direction' => [
                'label' => 'اتجاه الترتيب',

                'options' => [
                    'asc' => 'تصاعدي',

                    'desc' => 'تنازلي',
                ],
            ],
        ],
    ],

    'default_model_label' => 'سجل',

    'loading' => 'جارٍ التحميل...',

    'result_count' => '{0} لا توجد نتائج|{1} نتيجة واحدة|{2} نتيجتان|[3,10] :count نتائج|[11,*] :count نتيجة',

];
