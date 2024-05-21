<?php
namespace App\Actions\Wallet;

use App\Models\Wallet;
use App\Models\WalletHistory;
use Exception;

class DepositAction
{
   /**
     * Handle the deposit of funds into the user's wallet.
     *
     * @param string $walletIdentifier
     * @param float $amount
     * @return array
     * @throws \Exception
     */
    public function execute(string $walletIdentifier, float $amount, int $transactionId): array
    {
        try {
            $wallet = Wallet::where('uuid', $walletIdentifier)->lockForUpdate()->first();

            if (!$wallet) {
                throw new Exception('Wallet not found.');
            }

            $previousBalance = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            WalletHistory::create([
                'wallet_id' =>  $wallet->id,
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance,
                'amount' => $amount,
                'type' => WalletHistory::TYPE['DEPOSIT'],
                'transaction_id' => $transactionId
            ]);

            $data = [
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance
            ];
    
            return $data;

        } catch (Exception $e) {
            throw new Exception('Failed to deposit funds: ' . $e->getMessage());
        }
       
    }
}