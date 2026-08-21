<?php

declare(strict_types=1);

use PeanutAdmin\Kernel\Override\ServiceOverride;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;

$smsProvider = trim((string) (getenv('PEANUT_SMS_PROVIDER_IMPLEMENTATION') ?: ''));

return $smsProvider === '' ? [] : [
    new ServiceOverride(
        'peanut.notification.service.sms-provider',
        SmsProvider::class,
        '1.0.0',
        $smsProvider,
    ),
];
