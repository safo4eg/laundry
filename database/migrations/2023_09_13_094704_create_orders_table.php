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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('geo', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('address_desc')->nullable();
            $table->text('wishes')->nullable();
            $table->tinyInteger('status_id')->unsigned();
            $table->tinyInteger('laundry_id')->unsigned()->nullable();
            $table->unsignedTinyInteger('reason_id')->nullable();
            $table->boolean('active')->default(false);

            $table->foreignId('user_id')->references('id')->on('users');
            $table->foreign('status_id')->references('id')->on('statuses');
            $table->foreign('reason_id')->references('id')->on('reasons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
