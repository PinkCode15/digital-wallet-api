<?php

namespace App\PaymentProviders;

use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /**
     * Initialize payment 
     *
     * @param array $data
     * @return array
     */
    public function initiateDeposit(array $data): array;

    /**
     * Initialize withdraw 
     *
     * @param array $data
     * @return array
     */
    public function initiateWithdraw(array $data): array;

    /**
     * Verify Deposit
     *
     * @param string $reference
     * @param string $providerRef
     * @return array
     */
    public function verifyDeposit(string $reference, string $providerRef): array;

    /**
     * Verify Withdraw
     *
     * @param string $reference
     * @param string $providerRef
     * @return array
     */
    public function verifyWithdraw(string $reference, string $providerRef): array;

    /**
     * Validate Webhook
     *
     * @param Request $request
     * @return array
     */
    public function validateWebhook(Request $request): array;

    /**
     * Get Webhook type
     *
     * @param Request $request
     * @return string
     */
    public function getWebhookType(Request $request): string;
}