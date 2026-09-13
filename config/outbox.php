<?php

return [
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 10),
    'retry_delay_minutes' => (int) env('OUTBOX_RETRY_DELAY_MINUTES', 5),
];
