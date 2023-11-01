<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new
class () extends Migration {
    public function up(): void
    {
        Schema::create('telegraph_bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bot_id');
            $table->string('token')->unique();
            $table->string('first_name')->nullable();
            $table->string('username')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('telegraph_bots');
    }
};
