<?php
namespace App\Actions\Wallet;

use App\Models\Wallet;
use App\Models\WalletHistory;
use Exception;

class WithdrawAction
{
   /**
     * Handle the withdraw of funds from the user's wallet.
     *
     * @param string $walletIdentifier
     * @param float $amount
     * @param int $transactionId
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

            if ($wallet->balance < $amount) {
                throw new Exception('Insufficient funds.');
            }

            $previousBalance = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->save();

            WalletHistory::create([
                'wallet_id' =>  $wallet->id,
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance,
                'amount' => $amount,
                'type' => WalletHistory::TYPE['WITHDRAW'],
                'transaction_id' => $transactionId
            ]);

            $data = [
                'previous_balance' => $previousBalance,
                'current_balance' => $wallet->balance,
                'currency' => $wallet->currency
            ];
    
            return $data;

        } catch (Exception $e) {
            throw new Exception('Failed to withdraw funds: ' . $e->getMessage());
        }
       
    }
}
