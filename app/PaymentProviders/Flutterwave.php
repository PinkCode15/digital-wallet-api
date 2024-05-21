<?php
namespace App\PaymentProviders;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Flutterwave
{
    public $base_url;
    public $secret_key;
    public $public_key;
    public $secret_hash;

    /**
     * Initialize a new flutterwave instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->base_url = config('flutterwave.base_url');
        $this->secret_key = config('flutterwave.secret_key');
        $this->public_key = config('flutterwave.public_key');
        $this->secret_hash = config('flutterwave.secret_hash');
    }

   /**
    * Initialize payment 
    *
    * @param array $data
    * @return array
    */
    public function initiateDeposit(array $data): array
    {
        $url = "{$this->base_url}/payments";

        $requestData = [
            "currency" => $data['currency'],
            'amount' => $data['amount'],
            'tx_ref' => $data['reference'],
            'customer' => [
                'email' =>  $data['email'],
            ],
            'meta' => [
                'wallet_identifier' =>  $data['wallet_identifier'],
            ],
            'redirect_url' => config('wallet.deposit_callback_url') . "?walletIdentifier={$data['wallet_identifier']}&email={$data['email']}"
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secret_key}",
        ])->post(
            $url, $requestData
        )->object();

        if($response->status !== "success"){
            Log::debug("Flutterwave Error - " . json_encode($response));

            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::initiateDepositResponse($response->data->link, $data['reference']);
    }

    /**
    * Initialize withdraw 
    *
    * @param array $data
    * @return array
    */
    public function initiateWithdraw(array $data): array
    {
        $url = "{$this->base_url}/transfers";

        $requestData = [
            'account_bank' => $data['bank_detail']->bank_code,
            'account_number' => $data['bank_detail']->account_number,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'reference' => $data['reference'],
            'narration' => "{$data['currency']} Wallet Withdraw",
            'debit_currency' => $data['currency']
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secret_key}",
        ])->post(
            $url, $requestData
        )->object();

        if($response->status !== "success"){
            Log::debug("Paystack Error - " . json_encode($response));

            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::initiateWithdrawResponse($response->data->reference, $response->data->amount);
    }

    /**
    * Verify Deposit
    *
    * @param string $reference
    * @param string $providerRef
    * @return array
    */
    public function verifyDeposit(string $reference, string $providerRef): array
    {
        $url = "{$this->base_url}/transactions/{$providerRef}/verify";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secret_key}",
        ])->get(
            $url
        )->object();

        if($response->status !== "success"){
            Log::debug("Flutterwave Error - " . json_encode($response));
            
            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::verifyDepositResponse(
            $response->data->status, $response->data->tx_ref, $response->data->amount, $response->data->currency,
            $response->data->customer->email, $response->data->meta->wallet_identifier
        );
    }

    /**
    * Verify Wiithdraw
    *
    * @param string $reference
    * @param string $providerRef
    * @return array
    */
    public function verifyWithdraw(string $reference, string $providerRef): array
    {
        $url = "{$this->base_url}/transactions/{$providerRef}/verify";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secret_key}",
        ])->get(
            $url
        )->object();

        $response = json_decode(json_encode([
            "status" => "success",
            "message" => "Transaction fetched successfully",
            "data" => [
                "id" => 5359673,
                "tx_ref" => "TRF-BMP-20240521203147-QONUNA",
                "flw_ref" => "FLW-MOCK-bb6ea545c52d47e37ce3141fdaa5faf6",
                "device_fingerprint" => "6cc29071965fb165a2f726375bdd9124",
                "amount" => "300",
                "currency" => "NGN",
                "charged_amount" => 200,
                "app_fee" => 2.8,
                "merchant_fee" => 0,
                "processor_response" => "Approved. Successful",
                "auth_model" => "VBVSECURECODE",
                "ip" => "52.209.154.143",
                "narration" => "CARD Transaction ",
                "status" => "failed",
                "payment_type" => "card",
                "created_at" => "2024-05-20T13:53:50.000Z",
                "account_id" => 83951,
                "card" => [
                    "first_6digits" => "553188",
                    "last_4digits" => "2950",
                    "issuer" => " CREDIT",
                    "country" => "NIGERIA NG",
                    "type" => "MASTERCARD",
                    "token" => "flw-t1nf-ca601f1e9fa22366222f2c95346df625-m03k",
                    "expiry" => "09/32"
                ],
                "meta" => [ 
                    "__CheckoutInitAddress" => "https =>//ravemodal-dev.herokuapp.com/v3/hosted/pay",
                    "wallet_identifier" => "9c18c64e-ac03-421b-b7b9-eedaf88cf53d"
                ],
                "amount_settled" => 197.2,
                "customer" => [
                    "id" => 2413634,
                    "name" => "Anonymous customer",
                    "phone_number" => "N/A",
                    "email" => "admin@sample.com",
                    "created_at" => "2024-05-20T13:53:50.000Z"
                ]
            ]
        ]));

        if($response->data->status !== "success" && $response->message == strtolower("No transaction was found for this id")){
            return PaymentProvider::verifyWithdrawResponse(
                'failed', $reference
            );
        }

        if($response->status !== "success"){
            Log::debug("Flutterwave Error - " . json_encode($response));
            
            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::verifyWithdrawResponse(
            $response->data->status, $response->data->tx_ref, $response->data->amount, $response->data->currency
        );
    }

    /**
    * Validate Webhook
    *
    * @param Request $request
    * @return array
    */
    public function validateWebhook(Request $request): array
    {   
        $data = $request->all();

        if($request->header('verif-hash') !== $this->secret_hash) {
            $errorMessage = 'Failed to validate webhook';
            return PaymentProvider::errorResponse($errorMessage);
        }

        $providerRef = isset($data['id']) ? $data['id'] : 
            (isset($data['data']['id']) ? $data['data']['id'] : 
            $data['transfer']['id']);

        $reference = isset($data['txRef']) ? $data['txRef'] : 
        (isset($data['data']['reference']) ? $data['data']['reference'] : 
        $data['transfer']['reference']);

        return PaymentProvider::validateWebhookResponse($reference, $providerRef);
    }

    /**
    * Get Webhook type
    *
    * @param Request $request
    * @return string
    */
    public function getWebhookType(Request $request): string 
    {
        $body = $request->all();
        $type = $body['event.type'];

        switch (strtolower($type)) {
            case 'transfer':
                return 'withdraw';
            case 'account_transaction':
                return 'deposit';
            default:
                return 'deposit';
        }
    }
}