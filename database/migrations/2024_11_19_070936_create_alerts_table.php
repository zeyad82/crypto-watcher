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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_id')->constrained('cryptos')->onDelete('cascade'); // Foreign key to cryptos table
            $table->string('trend');
            $table->string('previous_trend')->nullable();
            $table->decimal('entry', 20, 8);
            $table->decimal('stop_loss', 20, 8);
            $table->decimal('tp1', 20, 8);
            $table->decimal('tp2', 20, 8);
            $table->decimal('tp3', 20, 8);
            $table->unsignedInteger('result')->nullable(); // -1 for hitting sl, 1 for tp1, 2 for tp2, 3 for tp3
            $table->decimal('highest_price', 20, 8)->nullable(); // Tracks the highest price since the alert
            $table->decimal('lowest_price', 20, 8)->nullable();  // Tracks the lowest price since the alert
            $table->string('status')->default('open'); // Tracks if the alert is open, closed, or partially hit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
