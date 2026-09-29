<?php

return [
    'code_digits' => 6,

    'expiration_minutes' => (int) env(
        'PASSWORD_RESET_CODE_EXPIRATION_MINUTES',
        10
    ),

    'max_attempts' => (int) env(
        'PASSWORD_RESET_CODE_MAX_ATTEMPTS',
        5
    ),

    'resend_cooldown_seconds' => (int) env(
        'PASSWORD_RESET_RESEND_COOLDOWN_SECONDS',
        60
    ),
];