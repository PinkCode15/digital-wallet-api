<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Models\IncomingWebhook;
use App\Actions\Wallet\DepositAction;
use App\Models\Wallet;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDepositWebhookAction
{
   /**
    * Process Webhook
    *
    * @param Request $request
    * @param string $depositProvider
    * @return JsonResponse
    */
    public function execute(Request $request, string $depositProvider): array
    {
        $webhookLog = IncomingWebhook::create([
            'request' => json_encode([
                'body' => $request->all()
            ])
        ]);

        DB::beginTransaction();

        try{

            $depositProvider = (new $depositProvider());
            $validationResponse = $depositProvider->validateWebhook($request); 

            if($validationResponse['status'] !== true) {
                $this->updateWebhookLog($webhookLog, 'Request could not be validated.');

                DB::commit();
                throw new Exception('Request could not be validated.');
            }

            $verifyPayment = $depositProvider->verifyDeposit($validationResponse['reference'], $validationResponse['provider_ref']); 

            if($verifyPayment['status'] !== true){
                $this->updateWebhookLog($webhookLog, 'Transaction could not be verified.');

                DB::commit();
                throw new Exception('Transaction could not be verified.');
            }

            if(Transaction::where('reference', $verifyPayment['reference'])->first()){
               $this->updateWebhookLog($webhookLog, 'Transaction already exists');

                DB::commit();
        
                throw new Exception('Transaction already exists.');
            }

            if($verifyPayment['transaction_status'] !== 'success'){
                $this->updateWebhookLog($webhookLog, 'OK - Transaction is not successful');

                DB::commit();
                return [];
            }

            $fee = $this->getDepositFee($verifyPayment['amount'], $verifyPayment['currency']);
            $amount = $verifyPayment['amount'] - $fee;

            $user = User::where('email', $verifyPayment['email'])->first();

            $transaction = $this->createTransaction($user->id, $verifyPayment['wallet_identifier'], $amount, $verifyPayment['currency'], $verifyPayment['reference'], $fee);
            
            (new DepositAction())->execute($verifyPayment['wallet_identifier'], $amount, $transaction->id);

            $this->updateWebhookLog($webhookLog, 'OK');

            DB::commit();
            return [];

        } catch(Exception $e) {
            DB::rollback();

            $this->updateWebhookLog($webhookLog, $e->getMessage());
            throw new Exception('Error occured processing webhook: '. $e->getMessage());
        }
       
    }


    /**
    * Create transaction
    *
    * @param int $userId
    * @param string $walletIdentifier
    * @param float $amount
    * @param string $currency
    * @param string $reference
    * @param float $fee
    * @return Transaction
    */
    private function createTransaction(int $userId, string $walletIdentifier, float $amount, string $currency, string $reference, float $fee): Transaction
    {
        $wallet =  Wallet::where('uuid', $walletIdentifier)->first();

        if (!$wallet) {
            throw new Exception('Wallet not found.');
        }

        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'amount' =>  $amount,
            'fee' => $fee,
            'currency' => $currency,
            'reference' => $reference,
            'type' =>  Transaction::TYPE['DEPOSIT'],
            'status' => Transaction::STATUS['SUCCESS'],
            'narration' => "{$currency} Wallet Deposit"
        ]);

        return $transaction;
    }

    /**
    * Update Webhook Log
    *
    * @param IncomingWebhook $webhookLog
    * @param string $response
    * @return array
    */
    private function updateWebhookLog(IncomingWebhook $webhookLog, string $response): void
    {
        $webhookLog->update([
            'response' => $response
        ]);
    }

    /**
    * Calculate the deposit fee
    *
    * @param float $amount
    * @param string $currency
    * @return float
    */
    private function getDepositFee(float $amount, string $currency): float
    {
        $minFee = config("fee.deposit.min.{$currency}");
        $maxFee = config("fee.deposit.max.{$currency}");
        $fee = (config('fee.deposit.percent') / 100) * $amount;

        if($fee < $minFee) {
            return $minFee;
        }

        if($fee > $maxFee){
            return $maxFee;
        }

       return $fee;
    }
}