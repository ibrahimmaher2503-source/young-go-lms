<?php

declare(strict_types=1);

/**
 * T-P8-06: refund policy. What a refund does to course access is a working default until the owner approves a policy
 * (PRD 17.3: "only under approved policy"). `full` revokes the access an order granted once the order is fully refunded,
 * `any` revokes on every executed refund, `none` never revokes (access is then handled by hand).
 */
return [
    'revoke_access' => 'full',
];
