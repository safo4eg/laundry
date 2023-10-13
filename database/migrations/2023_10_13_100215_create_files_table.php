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
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('ticket_item_id')->nullable();
            $table->unsignedTinyInteger('file_type_id');
            $table->text('path');

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('ticket_item_id')->references('id')->on('ticket_items');
            $table->foreign('file_type_id')->references('id')->on('file_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('photos');
    }
};
