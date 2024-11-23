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
            $table->decimal('open', 40, 8)->nullable(); // Open price
            $table->decimal('high', 40, 8)->nullable(); // High price
            $table->decimal('low', 40, 8)->nullable(); // Low price
            $table->decimal('close', 40, 8)->nullable(); // Close price
            $table->decimal('last_volume', 40, 8)->nullable(); // Volume for the candle
            $table->decimal('latest_price', 40, 8)->nullable(); // Latest price (close price)
            $table->float('price_change')->nullable();
            $table->string('timeframe')->default('15m')->index();
            
            // MA fields for volume
            $table->decimal('vma_15', 40, 8)->nullable(); // 15-period MA for volume
            $table->decimal('vma_25', 40, 8)->nullable(); // 25-period MA for volume
            $table->decimal('vma_50', 40, 8)->nullable(); // 50-period MA for volume

            // EMA fields for price
            $table->decimal('price_ema_15', 40, 8)->nullable(); // 15-period EMA for price
            $table->decimal('price_ema_25', 40, 8)->nullable(); // 25-period EMA for price
            $table->decimal('price_ema_50', 40, 8)->nullable(); // 50-period EMA for price

            $table->json('meta')->nullable();

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
