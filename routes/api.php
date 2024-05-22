<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use App\Models\Transaction;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);

    Route::group(['prefix' => 'wallets', 'middleware' => ['auth:sanctum']], function () {
        Route::get('', [WalletController::class, 'getUserWallets']);
        Route::get('/{walletIdentifier}', [WalletController::class, 'getWallet']);
        Route::get('/{walletIdentifier}/transactions', [WalletController::class, 'getWalletTransactions']);
        Route::post('/deposit', [WalletController::class, 'initiateDeposit']);
        Route::post('/withdraw', [WalletController::class, 'initiateWithdraw']);
    });

    Route::get('/transactions', [TransactionController::class, 'getUserTransactions'])->middleware(['auth:sanctum']);
    Route::post('/webhooks/{provider}', [WalletController::class, 'processWebhook']);   
});


