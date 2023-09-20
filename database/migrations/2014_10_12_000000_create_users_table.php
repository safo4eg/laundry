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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 32)->unique();
            $table->bigInteger('chat_id')->unique();
            $table->bigInteger('balance')->nullable();
            $table->string('phone_number', 12)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('language_code', 2)->nullable();
            $table->string('page', 64); // start, select_language, menu, scenario, accepted_order, order_wishes
            $table->tinyInteger('step_id')->unsigned()->index()->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
