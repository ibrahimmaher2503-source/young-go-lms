<?php

declare(strict_types=1);

return [
    'idempotency_key_invalid' => 'The request key is not valid.',
    'idempotency_conflict' => 'This request key was already used for a different order.',
    'payer_inactive' => 'Your account cannot place orders.',
    'market_unavailable' => 'Purchases are not available in this country yet.',
    'lines_invalid' => 'Choose between 1 and 20 courses.',
    'product_unavailable' => 'This product is not available for purchase.',
    'price_unavailable' => 'This course has no price in your country.',
    'learner_not_allowed' => 'You can only buy for yourself or a linked child.',
    'learner_inactive' => 'This learner account cannot receive courses.',
    'duplicate_line' => 'The same course is listed twice for the same learner.',
    'already_enrolled' => 'This learner already has access to this course.',
    'transition_invalid' => 'The order cannot move to that status.',
    'reason_invalid' => 'Enter a reason of 3 to 500 characters.',
    'order_closed' => 'A failed or cancelled order cannot be adjusted.',
    'discount_invalid' => 'The discount must be between zero and the subtotal.',
];
