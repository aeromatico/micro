<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id')->unique()->comment('aero_hello_accounts.id');
            $table->unsignedBigInteger('bot_id');
            $table->string('username')->nullable();
            $table->text('token')->comment('Cifrado con Crypt');
            $table->string('webhook_secret', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_telegram_bots');
    }
};
