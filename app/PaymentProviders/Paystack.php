<?php
namespace App\PaymentProviders;

use App\Models\RecipientCode;
use App\Models\UserBankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Paystack
{
    public $base_url;
    public $secret_key;
    public $public_key;

    /**
     * Initialize a new paystack instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->base_url = config('paystack.base_url');
        $this->secret_key = config('paystack.secret_key');
        $this->public_key = config('paystack.public_key');
    }

   /**
    * Initialize deposit
    *
    * @param array $data
    * @return array
    */
    public function initiateDeposit(array $data): array
    {
        $url = "{$this->base_url}/transaction/initialize";

        $requestData = [
            "currency" => $data['currency'],
            'amount' => $data['amount'] * 100, //amount is converted to kobo
            'email' => $data['email'],
            'reference' => $data['reference'],
            'metadata' => [
                'wallet_identifier' =>  $data['wallet_identifier'],
            ],
            'callback_url' => config('wallet.deposit_callback_url') . "?walletIdentifier={$data['wallet_identifier']}&email={$data['email']}"
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secret_key}",
        ])->post(
            $url, $requestData
        )->object();

        if($response->status !== true){
            Log::debug("Paystack Error - " . json_encode($response));

            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::initiateDepositResponse($response->data->authorization_url, $response->data->reference);
    }

     /**
    * Initialize withdraw 
    *
    * @param array $data
    * @return array
    */
    public function initiateWithdraw(array $data): array
    {
        $recipientCode = RecipientCode::getCode($data['bank_detail']->id, 'paystack');

        if(!$recipientCode){
            $response = $this->createRecipient($data['bank_detail']);

            if($response->status !== true){
                Log::debug("Paystack Error - " . json_encode($response));
    
                $errorMessage = isset($response->message) ? $response->message : null;
                return PaymentProvider::errorResponse($errorMessage);
            }

            $recipientCode = RecipientCode::create([
                'user_bank_detail_id' => $data['bank_detail']->id,
                'code' => $response->data->recipient_code,
                'provider' => 'paystack'
            ]);
        }

        $code = $recipientCode->code;

        $url = "{$this->base_url}/transfer";

        $requestData = [
            'source' => 'balance',
            'amount' => $data['amount'] * 100, //amount is converted to kobo
            'recipient' => $code,
            'currency' => $data['currency'],
            'reference' => $data['reference'],
            'reason' => "{$data['currency']} Wallet Withdraw"
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secret_key}",
        ])->post(
            $url, $requestData
        )->object();

        if($response->status !== true){
            Log::debug("Paystack Error - " . json_encode($response));

            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::initiateWithdrawResponse($response->data->transfer_code, $response->data->amount / 100);
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
        $url = "{$this->base_url}/transaction/verify/{$reference}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secret_key}",
        ])->get(
            $url
        )->object();

        if($response->status !== true){
            Log::debug("Paystack Error - " . json_encode($response));
            
            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::verifyDepositResponse(
            $response->data->status, $response->data->reference, $response->data->amount / 100, $response->data->currency,
            $response->data->customer->email, $response->data->metadata->wallet_identifier
        );
    }

    /**
    * Verify Withdraw
    *
    * @param string $reference
    * @param string $providerRef
    * @return array
    */
    public function verifyWithdraw(string $reference, string $providerRef): array
    {
        $url = "{$this->base_url}/transaction/verify/{$reference}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->secret_key}",
        ])->get(
            $url
        )->object();

        if($response->status !== true && isset($response->code) && $response->code !== "transaction_not_found"){
            return PaymentProvider::verifyWithdrawResponse(
                'failed', $reference
            );
        }

        if($response->status !== true){
            Log::debug("Paystack Error - " . json_encode($response));
            
            $errorMessage = isset($response->message) ? $response->message : null;
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::verifyWithdrawResponse(
            $response->data->status, $response->data->reference, $response->data->amount, $response->data->currency
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
        $data = json_encode(json_decode($request->getContent()));

        if($request->header('x-paystack-signature') !== hash_hmac('sha512', $data, $this->secret_key)) {
            $errorMessage = 'Failed to validate webhook';
            return PaymentProvider::errorResponse($errorMessage);
        }

        return PaymentProvider::validateWebhookResponse($request['data']['reference'], $request['data']['reference']);
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
        $type = strtolower($body['event']);

        switch (strtolower($type)) {
            case (Str::contains($type, 'transfer')):
                return 'withdraw';
            case (Str::contains($type, 'charge')):
                return 'deposit';
            default:
                return 'deposit';
        }
    }

    /**
    * Create Recipient 
    *
    * @param UserBankDetail $bankDetails
    * @return object|null
    */
    private function createRecipient(UserBankDetail $bankDetails): object|null
    {
        $url = "{$this->base_url}/transferrecipient";

        $requestData = [
            'type' => 'nuban',
            'name' => $bankDetails->account_name,
            'account_number' => $bankDetails->account_number,
            'bank_code' => $bankDetails->bank_code,
            'currency' => $bankDetails->currency
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->secret_key}",
        ])->post(
            $url, $requestData
        )->object();

        return $response;
    }
}