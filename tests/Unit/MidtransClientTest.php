<?php

namespace Tests\Unit;

use App\Services\Payment\MidtransClient;
use PHPUnit\Framework\TestCase;

class MidtransClientTest extends TestCase
{
    protected function client(): MidtransClient
    {
        return new MidtransClient(serverKey: 'test-server-key', clientKey: 'test-client-key', isProduction: false);
    }

    public function test_it_accepts_a_correctly_signed_payload(): void
    {
        $payload = [
            'order_id' => 'ORD-ABC123',
            'status_code' => '200',
            'gross_amount' => '150000.00',
        ];
        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key'
        );

        $this->assertTrue($this->client()->verifySignature($payload));
    }

    public function test_it_rejects_a_tampered_payload(): void
    {
        $payload = [
            'order_id' => 'ORD-ABC123',
            'status_code' => '200',
            'gross_amount' => '150000.00',
            'signature_key' => 'not-the-real-signature',
        ];

        $this->assertFalse($this->client()->verifySignature($payload));
    }
}
