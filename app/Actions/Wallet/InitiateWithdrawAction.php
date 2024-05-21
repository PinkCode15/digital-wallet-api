<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;

class InitiateWithdrawAction
{

   /**
    * Handle the withdraw initiation
    *
    * @param array $data
    * @return array
    */
    public function execute(array $data): array
    {
        $withdrawProvider = Transaction::getWithdrawProvider();

        DB::beginTransaction();

        try{

            $reference = Transaction::generateReference(); 

            $fee = $this->getWithdrawFee($data['amount'], $data['wallet']->currency);
            $amount = $data['amount'] + $fee;

            $transaction = $this->createTransaction($data['user']->id, $data['wallet']->id, $data['amount'], $data['wallet']->currency, $reference, $fee);

            (new WithdrawAction())->execute($data['wallet']->uuid, $amount, $transaction->id);

            $paymentData = [
                'reference' => $reference,
                'currency' => $data['wallet']->currency,
                'bank_detail' =>  $data['wallet']->userBankDetail,
                'wallet_identifier' => $data['wallet']->uuid,
                'amount' => $data['amount'],
            ];

            $response = (new $withdrawProvider())->initiateWithdraw($paymentData); 

            if($response['status'] !== true){
                throw new Exception('Unable to initiate withdraw.');
            }

            $data = [
                'reference' => $response['reference'],
                'amount' => $response['amount'],
                'currency' => $data['wallet']->currency
            ];

            DB::commit();
    
            return $data;      
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to initiate withdraw: ' . $e->getMessage());
        }
       
    }

    /**
    * Create transaction
    *
    * @param int $userId 
    * @param int $walletId
    * @param float $amount
    * @param string $currency
    * @param string $reference
    * @param float $fee
    * @return Transaction
    */
    private function createTransaction(int $userId, int $walletId, float $amount, string $currency, string $reference, float $fee): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'amount' =>  $amount,
            'fee' => $fee,
            'currency' => $currency,
            'reference' => $reference,
            'type' =>  Transaction::TYPE['WITHDRAW'],
            'status' => Transaction::STATUS['PENDING'],
            'narration' => "{$currency} Wallet Withdraw"
        ]);

        return $transaction;
    }

     /**
    * Calculate the withdraw fee
    *
    * @param float $amount
    * @param string $currency
    * @return float
    */
    private function getWithdrawFee(float $amount, string $currency): float
    {
        $minFee = config("fee.withdraw.min.{$currency}");
        $maxFee = config("fee.withdraw.max.{$currency}");
        $fee = (config('fee.withdraw.percent') / 100) * $amount;

        if($fee < $minFee) {
            return $minFee;
        }

        if($fee > $maxFee){
            return $maxFee;
        }

       return $fee;
    }
}