<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('telegraph_chats', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id');
            $table->string('name')->nullable();
            $table->unsignedTinyInteger('laundry_id')->nullable();

            $table->foreignId('telegraph_bot_id')->constrained('telegraph_bots')->cascadeOnDelete();
            $table->unique(['chat_id', 'telegraph_bot_id']);
            $table->foreign('laundry_id')->references('id')->on('laundries');
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegraph_chats');
    }
};
