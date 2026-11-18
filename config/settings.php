<?php

declare(strict_types=1);
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\CertificateSettings;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\LearningSettings;
use App\Domain\Settings\MarketingSettings;
use App\Domain\Settings\MarketsSettings;
use App\Domain\Settings\NotificationsSettings;
use App\Domain\Settings\PaymentsSettings;

return [
    'settings' => [
        GeneralSettings::class,
        MarketsSettings::class,
        LearningSettings::class,
        NotificationsSettings::class,
        AiSettings::class,
        CertificateSettings::class,
        MarketingSettings::class,
        PaymentsSettings::class,
    ],
];
