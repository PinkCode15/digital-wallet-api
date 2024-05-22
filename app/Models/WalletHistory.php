<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletHistory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const TYPE = [
        'DEPOSIT' => 'deposit',
        'WITHDRAW' => 'withdraw',
        'REVERSAL' => 'reversal',
        'TRANSFER_DEPOSIT' => 'transfer_deposit',
        'TRANSFER_WITHDRAW' => 'transfer_withdraw'
    ];

    /**
     * returns wallet that has the history
     *
     * @return BelongsTo
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * returns transaction tied to the history
     *
     * @return BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
