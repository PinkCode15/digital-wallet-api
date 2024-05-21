<?php

namespace App\Http\Controllers;

use App\Actions\Wallet\DepositAction;
use App\Actions\Wallet\InitiateDepositAction;
use App\Actions\Wallet\InitiateWithdrawAction;
use App\Actions\Wallet\ProcessDepositWebhookAction;
use App\Actions\Wallet\ProcessWebhookAction;
use App\Actions\Wallet\ProcessWithdrawWebhookAction;
use App\Actions\Wallet\WIthdrawAction;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\InitiateDepositRequest;
use App\Http\Requests\Wallet\InitiateWithdrawRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WalletController extends Controller
{
     /**
     * Retrieve user's wallet(s).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserWallets(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        try{
            return response()->json([
                'message' => 'Wallet(s) Retrieved Successful',
                'data' => [
                    'wallets' => $user->wallets
                ]
            ], 200); 
        } catch (Exception $e) {
            Log::error($e);

            return response()->json(['error' => 'Failed to retrieve wallet(s)'], 500);
        }
    }

     /**
     * Initiate deposit transaction.
     *
     * @param DepositRequest $request
     * @return JsonResponse
     */
    public function initiateDeposit(InitiateDepositRequest $request): JsonResponse
    {
        $data = $request->all();
        
        try{
            $user = Auth::user();
            $wallet = Wallet::where('uuid', $data['wallet_identifier'])->first();

            if($wallet->user_id !== $user->id){
                return response()->json([
                    'message' => 'Wallet does not belong to user',
                    'data' => []
                ], 403); 
            }

            $data['user'] = $user;
            $data['wallet'] = $wallet;

            $data = (new InitiateDepositAction())->execute($data);

            return response()->json([
                'message' => 'Deposit Initiated Successfully',
                'data' => $data
            ], 200); 
        } catch (Exception $e) {
            Log::error($e);

            return response()->json(['error' => 'Failed to deposit funds'], 500);
        }
    }

   
    /**
     * Initiate wwithdraw transaction.
     *
     * @param InitiateWithdrawRequest $request
     * @return JsonResponse
     */
    public function initiateWithdraw(InitiateWithdrawRequest $request): JsonResponse
    {
        $data = $request->all();
        
        try{
            $user = Auth::user();
            $wallet = Wallet::where('uuid', $data['wallet_identifier'])->first();

            if($wallet->user_id !== $user->id){
                return response()->json([
                    'message' => 'Wallet does not belong to user',
                    'data' => []
                ], 403); 
            }

            if(!Hash::check($data['transaction_pin'], $user->transaction_pin)){
                return response()->json([
                    'message' => 'Incorrect transaction pin',
                    'data' => []
                ], 403); 
            }

            $pendingWithdrawal = Transaction::where('wallet_id', $wallet->id)
                ->where('status', Transaction::STATUS['PENDING'])->first();

            if($pendingWithdrawal){
                return response()->json([
                    'message' => 'Pending wallet withdraw',
                    'data' => []
                ], 403); 

            }

            $data['user'] = $user;
            $data['wallet'] = $wallet;

            $data = (new InitiateWithdrawAction())->execute($data);

            return response()->json([
                'message' => 'Withdraw Initiated Successfully',
                'data' => $data
            ], 200); 
        } catch (Exception $e) {
            Log::error($e);

            return response()->json(['error' => 'Failed to withdraw funds'], 500);
        }
    }


    /**
     * Process incoming webhook.
     *
     * @param Request $request
     * @param string $paymentProvider
     * @return JsonResponse
     */
    public function processWebhook(Request $request, string $paymentProvider): JsonResponse
    {
        try{
            $paymentProvider = Transaction::getPaymentProvider($paymentProvider);

            $type = (new $paymentProvider())->getWebhookType($request);

            if ($type == 'deposit'){
                (new ProcessDepositWebhookAction())->execute($request, $paymentProvider);
            }else{
                (new ProcessWithdrawWebhookAction())->execute($request, $paymentProvider);
            }
            
            return response()->json([
                'message' => 'Webhook Processed Successfully',
                'data' => []
            ], 200); 
        } catch (Exception $e) {
            Log::error($e);

            return response()->json(['error' => 'Failed to process webhook'], 500);
        }
    }
}
