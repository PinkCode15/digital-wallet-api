<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const TYPE = [
        'DEPOSIT' => 'deposit',
        'WITHDRAW' => 'withdraw',
        'TRANSFER_DEPOSIT' => 'transfer_deposit',
        'TRANSFER_WITHDRAW' => 'transfer_withdraw'
    ];

    public const STATUS = [
        'SUCCESS' => 'success',
        'FAILED' => 'failed',
        'PENDING' => 'pending'
    ];

     /**
     * returns wallet of the transaction
     *
     * @return BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

     /**
     * returns user of the transaction
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
    * Generate transaction reference
    *
    * @return string
    */
    public static function generateReference()
    {
        return Str::upper(sprintf(
            '%s-%s-%s-%s',
            'TRF',
            'BMP',            
            now()->format('YmdHis'),
            Str::random(6)
        ));
    }

    /**
    * Retrieve deposit provider
    *
    * @return string
    */
    public static function getDepositProvider()
    {
        $depositProvider = "App\PaymentProviders\\" . config('providers.deposit_provider');

        return $depositProvider;
    }

    /**
    * Retrieve withdraw provider
    *
    * @return string
    */
    public static function getWithdrawProvider()
    {
        $withdrawProvider = "App\PaymentProviders\\" . config('providers.withdraw_provider');

        return $withdrawProvider;
    }

    /**
    * Retrieve deposit provider
    *
    * @param string $provider
    * @return string
    */
    public static function getPaymentProvider(string $provider)
    {
       
        $depositProvider = "App\PaymentProviders\\" . $provider;
    
        return $depositProvider;
    }
}
