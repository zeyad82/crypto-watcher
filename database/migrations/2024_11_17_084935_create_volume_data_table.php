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
        Schema::create('volume_data', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->foreignId('crypto_id')->constrained('cryptos')->onDelete('cascade'); // Foreign key to cryptos table
            $table->decimal('open', 20, 8)->nullable(); // Open price
            $table->decimal('high', 20, 8)->nullable(); // High price
            $table->decimal('low', 20, 8)->nullable(); // Low price
            $table->decimal('close', 20, 8)->nullable(); // Close price
            $table->decimal('last_volume', 20, 8)->nullable(); // Volume for the candle
            $table->decimal('latest_price', 20, 8)->nullable(); // Latest price (close price)
            
            // EMA fields for volume
            $table->decimal('ema_7', 20, 8)->nullable(); // 7-period EMA for volume
            $table->decimal('ema_15', 20, 8)->nullable(); // 15-period EMA for volume
            $table->decimal('ema_25', 20, 8)->nullable(); // 25-period EMA for volume
            $table->decimal('ema_50', 20, 8)->nullable(); // 50-period EMA for volume
            $table->decimal('ema_100', 20, 8)->nullable(); // 100-period EMA for volume

            // EMA fields for price
            $table->decimal('price_ema_7', 20, 8)->nullable(); // 7-period EMA for price
            $table->decimal('price_ema_15', 20, 8)->nullable(); // 15-period EMA for price
            $table->decimal('price_ema_25', 20, 8)->nullable(); // 25-period EMA for price
            $table->decimal('price_ema_50', 20, 8)->nullable(); // 50-period EMA for price
            $table->decimal('price_ema_100', 20, 8)->nullable(); // 100-period EMA for price

            $table->timestamp('timestamp'); // Timestamp of the recorded data
            $table->timestamps(); // Created and updated timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volume_data');
    }
};
