<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipientCode extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    
    /**
     * returns user bank details
     *
     * @return BelongsTo
     */
    public function userBankDetail()
    {
        return $this->belongsTo(UserBankDetail::class);
    }

    /**
     * returns recipient code
     *
     * @param int $bankDetailId
     * @param string $provider
     * @return self
     */
    public static function getCode(int $bankDetailId, string $provider): self|null
    {
        $code = self::where('user_bank_detail_id', $bankDetailId)->where('provider', $provider)->first();

        if(!$code){
            return null;
        }

        return $code;
    }
}
