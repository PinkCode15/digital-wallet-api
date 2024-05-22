<?php
namespace App\PaymentProviders;

class PaymentProviderResponse
{
   /**
    * Initialize payment 
    *
    * @param string $paymentUrl
    * @param string $reference
    * @return array
    */
    public static function initiateDepositResponse(string $paymentUrl, string $reference): array
    {
        return [
            'status' => true,
            'payment_url' => $paymentUrl,
            'reference' => $reference
        ];
    }

    /**
    * Initialize payment 
    *
    * @param string $reference
    * @param float $amount
    * @return array
    */
    public static function initiateWithdrawResponse(string $reference, float $amount): array
    {
        return [
            'status' => true,
            'reference' => $reference,
            'amount' => $amount,
        ];
    }

    /**
    * Verify Deposit
    *
    * @param string $transactionStatus
    * @param string $reference
    * @param float $amount
    * @param string $email
    * @param string $walletIdentifier
    * @return array
    */
    public static function verifyDepositResponse(
        string $transactionStatus, string $reference, float $amount, string $currency, string $email, string $walletIdentifier
    ): array
    {
        if (in_array(strtolower($transactionStatus), ['success', 'successful'])){
            $transactionStatus  = 'success';
        }

        return [
            'status' => true,
            'transaction_status' => $transactionStatus,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'email' => $email,
            'wallet_identifier' => $walletIdentifier
        ];
    }

      /**
    * Verify Withdraw
    *
    * @param string $transactionStatus
    * @param string $reference
    * @param float $amount
    * @param string $walletIdentifier
    * @return array
    */
    public static function verifyWithdrawResponse(
        string $transactionStatus, string $reference, float $amount = 0, string $currency = 'NGN'
    ): array
    {
        if (in_array(strtolower($transactionStatus), ['success', 'successful'])){
            $transactionStatus  = 'success';
        }
        else if(strtolower($transactionStatus) == 'failed'){
            $transactionStatus  = 'failed';
        }else{
            $transactionStatus  = 'pending';
        }

        return [
            'status' => true,
            'transaction_status' => $transactionStatus,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency
        ];
    }

    /**
    * Validate Webhook
    *
    * @param string $reference
    * @param string $providerRef
    * @return array
    */
    public static function validateWebhookResponse(string $reference, string $providerRef): array
    {
        return [
            'status' => true,
            'reference' => $reference,
            'provider_ref' => $providerRef
        ];
    }

    /**
    * Error Response
    *
    * @param string $errorMessage
    * @return array
    */
    public static function errorResponse(string $errorMessage): array
    {
        return [
            'status' => false,
            'error' => $errorMessage
        ];
    }
}