<?php

declare(strict_types=1);

/**
 * T-P9-11 (ED-022): demo dataset for local and staging demos. There is deliberately no override: production is refused
 * in code, and any environment not listed here is refused too. The password is public and only ever opens demo accounts.
 */
return [
    'environments' => ['local', 'staging', 'testing'],

    // Reserved ".test" domain: no demo address can ever receive real mail.
    'email_domain' => 'demo.younggo.test',

    'password' => 'DemoPass#2026',
];
