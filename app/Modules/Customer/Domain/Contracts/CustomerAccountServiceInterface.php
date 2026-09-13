<?php

namespace App\Modules\Customer\Domain\Contracts;

interface CustomerAccountServiceInterface
{
    /**
     * Create a customer account from checkout data and return the new user.
     *
     * @param array<string,mixed> $details
     */
    public function createFromGuestData(array $details): object;
}
