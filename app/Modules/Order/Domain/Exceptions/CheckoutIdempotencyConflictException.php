<?php

namespace App\Modules\Order\Domain\Exceptions;

use RuntimeException;

final class CheckoutIdempotencyConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The checkout idempotency key or guest token is already associated with another order.');
    }
}
