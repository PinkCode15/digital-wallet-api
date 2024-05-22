<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletHistory;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferFundsAction
{
   /**
     * Handle funds transfer between wallets.
     *
     * @param Wallet $source_wallet
     * @param Wallet $destination_wallet
     * @param float $amount
     * @return array
     * @throws \Exception
     */
    public function execute(Wallet $sourceWallet, Wallet $destinationWallet, float $amount): array
    {
        $user = Auth::user();

        DB::beginTransaction(); 

        try {
            
            if ($sourceWallet->balance < $amount) {
                throw new Exception('Insufficient funds.');
            }

            $reference = Transaction::generateReference(); 

            $fee = $this->getTransferFee($amount, $sourceWallet->currency);
            $totalAmount = $amount + $fee;

            $transaction = $this->createTransaction($user->id, $sourceWallet->id, $amount, $sourceWallet->currency, 
            $reference, $fee, Transaction::TYPE['TRANSFER_WITHDRAW']);
            
            $previousBalance = $sourceWallet->balance;
            $sourceWallet->balance -= $totalAmount;
            $sourceWallet->save();

            $this->createWalletHistory($sourceWallet->id, $previousBalance, $sourceWallet->balance, $totalAmount, $transaction->id, WalletHistory::TYPE['TRANSFER_WITHDRAW']);

            $reference = Transaction::generateReference(); 
            $totalAmount = $amount - $fee;

            $transaction = $this->createTransaction($user->id, $destinationWallet->id, $amount, $destinationWallet->currency, 
            $reference, $fee, Transaction::TYPE['TRANSFER_DEPOSIT']);
            
            $previousBalance = $destinationWallet->balance;
            $destinationWallet->balance += $totalAmount;
            $destinationWallet->save();

            $this->createWalletHistory($destinationWallet->id, $previousBalance, $destinationWallet->balance, $totalAmount, $transaction->id, WalletHistory::TYPE['TRANSFER_DEPOSIT']);

            DB::commit();  

            $data = [
                'previous_balance' => $previousBalance,
                'current_balance' => $sourceWallet->balance,
                'currency' => $sourceWallet->currency
            ];
    
            return $data;

        } catch (Exception $e) {
            DB::rollback(); 

            throw new Exception('Failed to withdraw funds: ' . $e->getMessage());
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
    * @param string $type
    * @return Transaction
    */
    private function createTransaction(int $userId, int $walletId, float $amount, string $currency, 
    string $reference, float $fee, string $type): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'amount' =>  $amount,
            'fee' => $fee,
            'currency' => $currency,
            'reference' => $reference,
            'type' =>  $type,
            'status' => Transaction::STATUS['SUCCESS'],
            'narration' => "{$currency} Wallet Transfer"
        ]);

        return $transaction;
    }

    /**
    * Create wallet history
    *
    * @param int $walletId 
    * @param float $previousBalance
    * @param float $currentBalance
    * @param float $amount
    * @param int $transactionId
    * @param string $type
    * @return void
    */
    private function createWalletHistory(int $walletId, float $previousBalance, float $currentBalance, 
    float $amount, int $transactionId, string $type): void
    {
        WalletHistory::create([
            'wallet_id' =>  $walletId,
            'previous_balance' => $previousBalance,
            'current_balance' => $currentBalance,
            'amount' => $amount,
            'type' => $type,
            'transaction_id' => $transactionId
        ]);
    }

     /**
    * Calculate the transfer fee
    *
    * @param float $amount
    * @param string $currency
    * @return float
    */
    private function getTransferFee(float $amount, string $currency): float
    {
        $minFee = config("fee.transfer.min.{$currency}");
        $maxFee = config("fee.transfer.max.{$currency}");
        $fee = (config('fee.transfer.percent') / 100) * $amount;

        if($fee < $minFee) {
            return $minFee;
        }

        if($fee > $maxFee){
            return $maxFee;
        }

       return $fee;
    }
}