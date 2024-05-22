<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::orderedUuid();
        });
    }

     /**
     * returns user of the wallet
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * returns wallet history
     *
     * @return HasMany
     */
    public function walletHistories()
    {
        return $this->hasMany(WalletHistory::class);
    }

    /**
     * returns bank details
     *
     * @return HasOne
     */
    public function userBankDetail()
    {
        return $this->hasOne(UserBankDetail::class);
    }

     /**
     * returns wallet transactions
     *
     * @return HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

}
