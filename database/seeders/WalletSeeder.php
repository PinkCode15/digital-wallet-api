<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserBankDetail;
use App\Models\Wallet;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::where('email', 'admin@sample.com')->first();

        $wallet = Wallet::updateOrCreate([
            'user_id' => $user->id,
            'currency' => 'NGN',
        ],[
            'balance' => 500,
            'transaction_limit' => 500000
        ]);

        UserBankDetail::updateOrCreate([
            'wallet_id' => $wallet->id,
            'account_number' => '0690000032'
        ],[
            'account_name' => 'Bumpa Admin',
            'bank_code' => '044',
            'currency' => 'NGN'
        ]);

        $secondUser = User::where('email', 'nancy@sample.com')->first();

        Wallet::updateOrCreate([
            'user_id' => $secondUser->id,
            'currency' => 'NGN',
        ],[
            'balance' => 300,
            'transaction_limit' => 500000
        ]);

    }
}