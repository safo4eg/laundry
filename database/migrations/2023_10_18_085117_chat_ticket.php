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
        Schema::create('chat_ticket', function (Blueprint $table) {
            $table->unsignedBigInteger('telegraph_chat_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedTinyInteger('message_type_id');

            $table->foreign('telegraph_chat_id')->references('id')->on('telegraph_chats');
            $table->foreign('ticket_id')->references('id')->on('tickets');
            $table->foreign('message_type_id')->references('id')->on('message_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
