<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum PlatformFlag: string
{
    case AiAssistant = 'ai.assistant';
    case AiMinors = 'ai.minors';
    case SaudiCheckout = 'markets.sa_checkout';
    case SubscriptionAutoRenew = 'subscriptions.auto_renew';
    case Whatsapp = 'notifications.whatsapp';
    case PaymobLive = 'payments.paymob_live';
    case Ereceipt = 'ereceipt.enabled';
}
