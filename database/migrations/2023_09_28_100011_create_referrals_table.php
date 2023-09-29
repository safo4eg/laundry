<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id');
            $table->unsignedBigInteger('invited_id');
            $table->unsignedBigInteger('bonuses')->nullable();
            $table->timestamp('created_at')->default(Carbon::now());

            $table->foreign('inviter_id')->references('id')->on('users');
            $table->foreign('invited_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('referrals');
    }
};
