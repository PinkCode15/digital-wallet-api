<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recipient_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_bank_detail_id');
            $table->string('code');
            $table->string('provider');
            $table->timestamps();

            $table->foreign('user_bank_detail_id')->references('id')->on('user_bank_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recipient_codes');
    }
};
