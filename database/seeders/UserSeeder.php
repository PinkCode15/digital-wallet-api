<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::updateOrCreate([
            'email' => 'admin@sample.com',
        ],[
            'name' => 'Bumpa Admin',
            'password' => bcrypt('bumpa123'),
            'transaction_pin' => bcrypt('514505')
        ]);
    }
}