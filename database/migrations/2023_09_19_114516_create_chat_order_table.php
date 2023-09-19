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
        Schema::create('chat_order', function (Blueprint $table) {
            $table->unsignedBigInteger('telegraph_chat_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('message_id');

            $table->foreign('telegraph_chat_id')->references('id')->on('telegraph_chats');
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chat_order');
    }
};
