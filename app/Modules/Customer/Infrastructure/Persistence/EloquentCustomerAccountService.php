<?php

namespace App\Modules\Customer\Infrastructure\Persistence;

use App\Models\Role;
use App\Models\User;
use App\Modules\Customer\Domain\Contracts\CustomerAccountServiceInterface;
use App\Modules\Customer\Domain\Exceptions\CustomerAccountConflictException;
use Illuminate\Support\Facades\Hash;

final class EloquentCustomerAccountService implements CustomerAccountServiceInterface
{
    public function createFromGuestData(array $details): object
    {
        $email = isset($details['email']) ? strtolower(trim((string) $details['email'])) : null;
        $phone = isset($details['phone']) ? trim((string) $details['phone']) : null;

        if ($email === null || $email === '') {
            throw new CustomerAccountConflictException('An email address is required to create a customer account.');
        }

        if (User::query()->where('email', $email)->exists() || ($phone !== null && User::query()->where('phone', $phone)->exists())) {
            throw new CustomerAccountConflictException('An account already exists for this email or phone. Please sign in or continue as a guest.');
        }

        $password = (string) ($details['password'] ?? '');
        if ($password === '') {
            throw new CustomerAccountConflictException('A password is required when creating a customer account.');
        }

        $user = User::query()->create([
            'name' => (string) $details['name'],
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);

        $customerRole = Role::query()->where('slug', 'customer')->first();
        if ($customerRole !== null) {
            $user->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        $user->addresses()->create([
            'label' => 'checkout',
            'recipient_name' => (string) $details['name'],
            'phone' => $phone,
            'address_line1' => (string) $details['address_line1'],
            'address_line2' => $details['address_line2'] ?? null,
            'city' => (string) $details['city'],
            'state' => $details['state'] ?? null,
            'postal_code' => $details['postal_code'] ?? null,
            'country' => strtoupper((string) ($details['country'] ?? 'EG')),
            'is_default' => true,
        ]);

        return $user->fresh();
    }
}
