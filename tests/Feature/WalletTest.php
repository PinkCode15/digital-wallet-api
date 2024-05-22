<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Illuminate\Support\Str;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected $transactionPin;
    protected $user;
    protected $flutterwaveBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh --env=testing');

        $this->transactionPin = '515278';
        $this->user = User::factory()->create([
            'transaction_pin' => bcrypt($this->transactionPin)
        ]);
        $this->flutterwaveBaseUrl = config('flutterwave.base_url');

        $this->actingAs($this->user);
    }

    /**
     * Test deposit initiation.
     *
     * @return void
     */
    public function test_intiate_deposit()
    {
        $this->mockDepositHttpResponse();

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
        $this->mockWithdrawHttpResponse();

        $initialBalance = 1000;
        $amount = 100;

        $wallet = $this->user->wallets()->create([
            'balance' => 1000,
            'currency' => 'NGN',
        ]);

        $wallet->userBankDetail()->create([
            'account_number' => '0690000032',
            'account_name' => 'Bumpa Admin',
            'bank_code' => '044',
            'currency' => 'NGN'
        ]);

        $response = $this->post('/api/v1/wallets/withdraw', [
            "amount" => $amount,
            "wallet_identifier" => $wallet->uuid,
            "transaction_pin" => $this->transactionPin
        ]);

        $wallet->refresh();
        $response->assertStatus(200);

        $amount = $initialBalance - ($amount + $this->getFee($amount, 'NGN', 'withdraw'));
        $this->assertEquals($wallet->balance, $amount);
        $this->assertArrayHasKey('reference', $response->json()['data']);
    }

    /**
     * Test deposit.
     *
     * @return void
     */
    public function test_deposit()
    {
        $reference = Transaction::generateReference();
        $providerRef = random_int(1111111,9999999);
        $initialBalance = 1000;
        $amount = 800;

        $wallet = $this->user->wallets()->create([
            'balance' => $initialBalance ,
            'currency' => 'NGN',
        ]);

        $this->mockVerifyHttpResponse($reference, $amount, $providerRef, $wallet->uuid);

        $webhookData = [
            "id" => $providerRef,
            "txRef" => $reference,
            "flwRef" => "1716213610836-FLW-MOCK-REF",
            "orderRef" => "URF_1716213610561_4791035",
            "paymentPlan" => null,
            "paymentPage" => null,
            "createdAt" => "2024-05-20T14:00:10.000Z",
            "amount" => $amount,
            "charged_amount" => 200,
            "status" => "successful",
            "IP" => "54.75.161.64",
            "currency" => "NGN",
            "appfee" => 2.8,
            "merchantfee" => 0,
            "merchantbearsfee" => 1,
            "customer" => [
              "id" => 2413643,
              "phone" => null,
              "fullName" => "Anonymous Customer",
              "customertoken" => null,
              "email" => "admin@sample.com",
              "createdAt" => "2024-05-20T14:00:10.000Z",
              "updatedAt" => "2024-05-20T14:00:10.000Z",
              "deletedAt" => null,
              "AccountId" => 83951
            ],
            "entity" => [
              "id" => "NO-ENTITY"
            ],
            "event.type" => "ACCOUNT_TRANSACTION"
        ];
        $webhookHeader = [
            'verif-hash' => config('flutterwave.secret_hash')
        ];

        $response = $this->post('/api/v1/webhooks/flutterwave', $webhookData, $webhookHeader);

        $wallet->refresh();
        $response->assertStatus(200);

        $amount = $initialBalance + ($amount - $this->getFee($amount, 'NGN', 'deposit'));
        $this->assertEquals($wallet->balance, $amount);
    }

    /**
     * Test withdraw.
     *
     * @return void
     */
    public function test_withdraw()
    {
        $this->mockWithdrawHttpResponse();

        $providerRef = random_int(1111111,9999999);
        $initialBalance = 1000;
        $amount = 800;

        $wallet = $this->user->wallets()->create([
            'balance' => $initialBalance ,
            'currency' => 'NGN',
        ]);

        $wallet->userBankDetail()->create([
            'account_number' => '0690000032',
            'account_name' => 'Bumpa Admin',
            'bank_code' => '044',
            'currency' => 'NGN'
        ]);

        $response = $this->post('/api/v1/wallets/withdraw', [
            "amount" => $amount,
            "wallet_identifier" => $wallet->uuid,
            "transaction_pin" => $this->transactionPin
        ]);

        $reference = $response->getData()->data->reference;

        $this->mockVerifyHttpResponse($reference, $amount, $providerRef, $wallet->uuid);

        $webhookData = [
            "event" => "transfer.completed",
            "event.type" => "Transfer",
            "data" => [ 
                "id" => $providerRef,
                "account_number" => "******",
                "bank_name" => "ACCESS BANK NIGERIA",
                "bank_code" => "044",
                "fullname" => "Bumpa Admin",
                "created_at" => "2021-04-28T17:01:41.000Z",
                "currency" => "NGN",
                "debit_currency" => "NGN",
                "amount" => $amount,
                "fee" => 10.75,
                "status" => "SUCCESSFUL",
                "reference" => $reference,
                "meta" => null,
                "narration" => "Test for wallet to wallet",
                "approver" => null,
                "complete_message" => "Transaction was successful",
                "requires_approval" => 0,
                "is_approved" => 1
            ]
        ];

        $webhookHeader = [
            'verif-hash' => config('flutterwave.secret_hash')
        ];

        $response = $this->post('/api/v1/webhooks/flutterwave', $webhookData, $webhookHeader);

        $wallet->refresh();
        $response->assertStatus(200);

        $amount = $initialBalance - ($amount + $this->getFee($amount, 'NGN', 'withdraw'));
        $this->assertEquals($wallet->balance, $amount);
    }

    /**
     * Test reversal.
     *
     * @return void
     */
    public function test_reversal()
    {
        $this->mockWithdrawHttpResponse();

        $providerRef = random_int(1111111,9999999);
        $initialBalance = 1000;
        $amount = 800;

        $wallet = $this->user->wallets()->create([
            'balance' => $initialBalance ,
            'currency' => 'NGN',
        ]);

        $wallet->userBankDetail()->create([
            'account_number' => '0690000032',
            'account_name' => 'Bumpa Admin',
            'bank_code' => '044',
            'currency' => 'NGN'
        ]);

        $response = $this->post('/api/v1/wallets/withdraw', [
            "amount" => $amount,
            "wallet_identifier" => $wallet->uuid,
            "transaction_pin" => $this->transactionPin
        ]);

        $reference = $response->getData()->data->reference;

        $this->mockVerifyHttpResponse($reference, $amount, $providerRef, $wallet->uuid, 'failed');

        $webhookData = [
            "event" => "transfer.completed",
            "event.type" => "Transfer",
            "data" => [ 
                "id" => $providerRef,
                "account_number" => "******",
                "bank_name" => "ACCESS BANK NIGERIA",
                "bank_code" => "044",
                "fullname" => "Bumpa Admin",
                "created_at" => "2021-04-28T17:01:41.000Z",
                "currency" => "NGN",
                "debit_currency" => "NGN",
                "amount" => $amount,
                "fee" => 10.75,
                "status" => "FAILED",
                "reference" => $reference,
                "meta" => null,
                "narration" => "Test for wallet to wallet",
                "approver" => null,
                "complete_message" => "Transaction failed",
                "requires_approval" => 0,
                "is_approved" => 1
            ]
        ];

        $webhookHeader = [
            'verif-hash' => config('flutterwave.secret_hash')
        ];

        $response = $this->post('/api/v1/webhooks/flutterwave', $webhookData, $webhookHeader);

        $wallet->refresh();
        $response->assertStatus(200);

        $this->assertEquals($wallet->balance, $initialBalance);
    }

     /**
     * Test wallet balance retrieval.
     *
     * @return void
     */
    public function test_get_wallet_balance()
    {
        $initialBalance = 4000;

        $wallet = $this->user->wallets()->create([
            'balance' => $initialBalance,
            'currency' => 'NGN',
        ]);

        $response = $this->get("/api/v1/wallets/{$wallet->uuid}");

        $response->assertStatus(200);

        $this->assertArrayHasKey('balance', $response->json()['data']['wallet']);
        $this->assertEquals($wallet->balance, $initialBalance);
    }

    /**
     * Mock Http response.
     *
     * @return void
     */
    private function mockDepositHttpResponse() 
    {
        Http::fake([
            "{$this->flutterwaveBaseUrl}/payments" => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link',
                'data' => [
                    'link' => 'https://ravemodal-dev.herokuapp.com/v3/hosted/pay/69f3f0a9dc314b389cb8',
                ]
            ], 200),
        ]);
    }

     /**
     * Mock Http response.
     *
     * @return void
     */
    private function mockWithdrawHttpResponse($reference = "TRF-BMP-20240521213240-ZZ4DTV", $amount = 100)
    {
        Http::fake([
            "{$this->flutterwaveBaseUrl}/transfers" => Http::response([
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
        ]);
    }

     /**
     * Mock Http response.
     *
     * @return void
     */
    private function mockVerifyHttpResponse($reference, $amount, 
        $providerRef, $walletIdentifier, $status = "successful"
    ){

        Http::fake([
            "{$this->flutterwaveBaseUrl}/transactions/{$providerRef}/verify" => Http::response([
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
                        'wallet_identifier' => $walletIdentifier,
                    ],
                    'amount_settled' => 1972,
                    'customer' => [
                        'id' => 2414842,
                        'name' => 'Anonymous Customer',
                        'phone_number' => 'N/A',
                        'email' => $this->user->email,
                        'created_at' => '2024-05-21T21:33:15.000Z'
                    ]
                ]
            ], 200)
        ]);

    }

    private function getFee(float $amount, string $currency, string $type): float
    {
        $minFee = config("fee.{$type}.min.{$currency}");
        $maxFee = config("fee.{$type}.max.{$currency}");
        $fee = (config("fee.{$type}.percent") / 100) * $amount;

        if($fee < $minFee) {
            return $minFee;
        }

        if($fee > $maxFee){
            return $maxFee;
        }

       return $fee;
    }
}
