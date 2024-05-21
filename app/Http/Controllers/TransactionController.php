<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
     /**
     * Retrieve user's transaction(s).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserTransactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        try{
            return response()->json([
                'message' => 'Transaction(s) Retrieved Successful',
                'data' => [
                    'transactions' => $user->transactions()->orderBy('created_at', 'desc')->paginate($request->length ?? 20)
                ]
            ], 200); 
        } catch (Exception $e) {
            Log::error($e);

            return response()->json(['error' => 'Failed to retrieve transaction(s)'], 500);
        }
    }
}
