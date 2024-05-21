<?php
namespace App\Actions\Wallet;

use App\Models\Transaction;
use App\Models\IncomingWebhook;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawWebhookAction
{
   /**
    * Process Webhook
    *
    * @param Request $request
    * @param string $withdrawProvider
    * @return JsonResponse
    */
    public function execute(Request $request, string $withdrawProvider): array
    {
        $webhookLog = IncomingWebhook::create([
            'request' => json_encode([
                'body' => $request->all()
            ])
        ]);

        DB::beginTransaction();

        try{

            $withdrawProvider = (new $withdrawProvider());
            $validationResponse = $withdrawProvider->validateWebhook($request); 

            if ($validationResponse['status'] !== true) {
                $this->updateWebhookLog($webhookLog, 'Request could not be validated.');

                DB::commit();
                throw new Exception('Request could not be validated.');
            }

            $verifyPayment = $withdrawProvider->verifyWithdraw($validationResponse['reference'], $validationResponse['provider_ref']); 

            if ($verifyPayment['status'] !== true) {
                $this->updateWebhookLog($webhookLog, 'Transaction could not be verified.');

                DB::commit();
                throw new Exception('Transaction could not be verified.');
            }

            $transaction = Transaction::where('reference', $verifyPayment['reference'])->first();

            if(!$transaction){
               $this->updateWebhookLog($webhookLog, 'Transaction does not exist');

                DB::commit();
                throw new Exception('Transaction does not exist.');
            }

            if(in_array($transaction->status, ['failed', 'success'])){
                $this->updateWebhookLog($webhookLog, 'OK - Transaction already completed');
 
                DB::commit();
                return [];
            }

            if($verifyPayment['transaction_status'] == 'failed'){
                $transaction->update([
                    'status' => Transaction::STATUS['FAILED']
                ]);

                (new ReversalAction())->execute($transaction->id);

            } else if ($verifyPayment['transaction_status'] == 'success'){
                $transaction->update([
                    'status' => Transaction::STATUS['SUCCESS']
                ]);
            }
            
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
}