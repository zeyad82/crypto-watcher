<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cryptos', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // Unique trading pair symbol
            $table->string('base_asset');      // Base asset (e.g., BTC)
            $table->string('quote_asset');     // Quote asset (e.g., USDT)
            $table->string('last_trend')->nullable();
            $table->timestamp('last_volume_alert')->nullable();
            $table->timestamp('last_fetched')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cryptos');
    }
};
