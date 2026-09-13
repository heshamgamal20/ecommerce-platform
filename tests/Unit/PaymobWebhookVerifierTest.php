<?php

namespace Tests\Unit;

use App\Modules\Payment\Infrastructure\Configuration\PaymentGatewaySettings;
use App\Modules\Payment\Infrastructure\Webhooks\PaymobWebhookVerifier;
use App\Modules\Settings\Domain\Contracts\SettingsRepositoryInterface;
use App\Modules\Settings\Domain\ValueObjects\SettingData;
use Tests\TestCase;

final class PaymobWebhookVerifierTest extends TestCase
{
    public function test_it_verifies_paymob_transaction_hmac_in_documented_field_order(): void
    {
        $secret = 'sandbox-hmac-secret';
        $object = [
            'amount_cents' => 1500,
            'created_at' => '2026-09-13T12:00:00.000000+00:00',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 987654,
            'integration_id' => 1234,
            'is_3d_secure' => false,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 456789],
            'owner' => 1,
            'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'Visa', 'type' => 'card'],
            'success' => true,
        ];
        $values = [
            $object['amount_cents'], $object['created_at'], $object['currency'], $object['error_occured'],
            $object['has_parent_transaction'], $object['id'], $object['integration_id'], $object['is_3d_secure'],
            $object['is_auth'], $object['is_capture'], $object['is_refunded'], $object['is_standalone_payment'],
            $object['is_voided'], $object['order']['id'], $object['owner'], $object['pending'],
            $object['source_data']['pan'], $object['source_data']['sub_type'], $object['source_data']['type'], $object['success'],
        ];
        $values = array_map(static fn (mixed $value): string => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $values);
        $hmac = hash_hmac('sha512', implode('', $values), $secret);

        $verifier = new PaymobWebhookVerifier(new PaymentGatewaySettings(new FakeSettingsRepository($secret)));

        self::assertTrue($verifier->verify(['obj' => $object], $hmac));
    }
}

final class FakeSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(private readonly string $secret) {}

    public function findByKey(string $key): ?object
    {
        return new class($this->secret) {
            public function __construct(private readonly string $value) {}
            public function getTypedValue(): string { return $this->value; }
        };
    }

    public function getByGroup(string $group): iterable { return []; }
    public function getAll(): iterable { return []; }
    public function save(SettingData $data): object { return $data; }
    public function delete(string $key): bool { return true; }
}
