<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Illuminate\Support\Str;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh --env=testing');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * Test deposit initiation.
     *
     * @return void
     */
    public function test_intiate_deposit()
    {
        $this->mockHttpResponses();

        $wallet = $this->user->wallets()->create([
            'balance' => 1000,
            'currency' => 'NGN',
        ]);

        $response = $this->post('/api/v1/wallets/deposit', [
            "amount" => 2000,
            "wallet_identifier" => $wallet->uuid
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertArrayHasKey('payment_url', $responseData['data']);
    }

    /**
     * Test withdraw initiation.
     *
     * @return void
     */
    public function test_intiate_withdraw()
    {
        $this->mockHttpResponses();

        $wallet = $this->user->wallets()->create([
            'balance' => 1000,
            'currency' => 'NGN',
        ]);

        $response = $this->post('/api/v1/wallets/withdraw', [
            "amount" => 2000,
            "wallet_identifier" => $wallet->uuid,
            "transaction_pin" => $this->user->transaction_pin
        ]);

        dd($response);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertArrayHasKey('payment_url', $responseData['data']);
    }

    private function mockHttpResponses($reference = "TRF-BMP-20240521213240-ZZ4DTV", $amount = 100, $providerRef = "1234TY", $status = "successful"){
        $flutterwaveBaseUrl = config('flutterwave.base_url');

        Http::fake([
            "{$flutterwaveBaseUrl}/payments" => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link',
                'data' => [
                    'link' => 'https://ravemodal-dev.herokuapp.com/v3/hosted/pay/69f3f0a9dc314b389cb8',
                ]
            ], 200),

            "{$flutterwaveBaseUrl}/transfers" => Http::response([
                'status' => 'success',
                'message' => 'Transfer Queued Successfully',
                'data' => [
                    'id' => 629084,
                    'account_number' => '0690000032',
                    'bank_code' => '044',
                    'full_name' => 'Pastor Bright',
                    'created_at' => '2024-05-21T21:29:28.000Z',
                    'currency' => 'NGN',
                    'debit_currency' => 'NGN',
                    'amount' => $amount,
                    'fee' => 10.75,
                    'status' => 'NEW',
                    'reference' => $reference,
                    'meta' => NULL,
                    'narration' => 'NGN Wallet Withdraw',
                    'complete_message' => '',
                    'requires_approval' => 0,
                    'is_approved' => 1,
                    'bank_name' => 'ACCESS BANK NIGERIA',
                ]
            ], 200),

            "{$flutterwaveBaseUrl}/transactions/{$providerRef}/verify" => Http::response([
                'status' => 'success',
                'message' => 'Transaction fetched successfully',
                'data' => [
                    'id' => 5422617,
                    'tx_ref' => $reference,
                    'flw_ref' => '1716327195621-FLW-MOCK-REF',
                    'device_fingerprint' => '6cc29071965fb165a2f726375bdd9124',
                    'amount' => $amount,
                    'currency' => 'NGN',
                    'charged_amount' => 2000,
                    'app_fee' => 28,
                    'merchant_fee' => 0,
                    'processor_response' => 'Successful',
                    'auth_model' => 'INTERNET_BANKING',
                    'ip' => '54.75.161.64',
                    'narration' => 'Calla Charm',
                    'status' => $status,
                    'payment_type' => 'account',
                    'created_at' => '2024-05-21T21:33:15.000Z',
                    'account_id' => 83951,
                    'meta' => [
                        '__CheckoutInitAddress' => 'https://ravemodal-dev.herokuapp.com/v3/hosted/pay',
                        'wallet_identifier' => '9c1942b0-eb5e-4abe-9374-c8b018aaea79',
                    ],
                    'amount_settled' => 1972,
                    'customer' => [
                        'id' => 2414842,
                        'name' => 'Anonymous Customer',
                        'phone_number' => 'N/A',
                        'email' => 'admin@sample.com',
                        'created_at' => '2024-05-21T21:33:15.000Z'
                    ]
                ]
            ], 200)
        ]);

    }
}
