<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use App\Models\WalletHistory;
use Exception;

class ReversalAction
{
   /**
     * Handle the reversal of funds into the user's wallet.
     * 
     * @param int $transactionId
     * @return array
     * @throws \Exception
     */
    public function execute(int $transactionId): array
    {
        try {
            
            $transaction = Transaction::where('id', $transactionId)->first();

            if (!$transaction) {
                throw new Exception('Transaction not found.');
            }

            $wallet =  $transaction->wallet->lockForUpdate()->first();

            if (!$wallet) {
                throw new Exception('Wallet not found.');
            }

            $previousBalance = $wallet->balance;
            $wallet->balance += $transaction->amount + $transaction->fee;
            $wallet->save();

            WalletHistory::create([
                'wallet_id' =>  $wallet->id,
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance,
                'amount' => $transaction->amount + $transaction->fee,
                'type' => WalletHistory::TYPE['REVERSAL'],
                'transaction_id' => $transactionId
            ]);

            $data = [
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance
            ];
    
            return $data;

        } catch (Exception $e) {
            throw new Exception('Failed to reverse funds: ' . $e->getMessage());
        }
       
    }
}