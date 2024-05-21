<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use Exception;

class InitiateDepositAction
{

   /**
    * Handle the deposit initiation
    *
    * @param array $data
    * @return array
    */
    public function execute(array $data): array
    {
        $depositProvider = Transaction::getDepositProvider();

        try{

            $paymentData = [
                'reference' => Transaction::generateReference(),
                'currency' => $data['wallet']->currency,
                'email' => $data['user']->email,
                'wallet_identifier' =>  $data['wallet']->uuid,
                'amount' => $data['amount']
            ];

            $response = (new $depositProvider())->initiateDeposit($paymentData); 

            if($response['status'] !== true){
                throw new Exception('Unable to initiate deposit.');
            }

            $data = [
                'payment_url' => $response['payment_url'],
                'reference' => $response['reference']
            ];
    
            return $data;      
        } catch(Exception $e) {
            throw new Exception('Failed to initiate deposit: ' . $e->getMessage());
        }
       
    }
}