<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VolumeData extends Model
{
    use HasFactory;

    protected $fillable = [
        'crypto_id',    // Foreign key referencing Crypto table
        'open',         // Open price for the candle
        'high',         // High price for the candle
        'low',          // Low price for the candle
        'close',        // Close price for the candle
        'last_volume',  // Volume for the candle
        'latest_price', // The latest price (usually the same as close)
        'ema_7',        // 7-period EMA for volume
        'ema_15',       // 15-period EMA for volume
        'ema_25',       // 25-period EMA for volume
        'ema_50',       // 50-period EMA for volume
        'ema_100',      // 100-period EMA for volume
        'price_ema_7',  // 7-period EMA for price
        'price_ema_15', // 15-period EMA for price
        'price_ema_25', // 25-period EMA for price
        'price_ema_50', // 50-period EMA for price
        'price_ema_100',// 100-period EMA for price
        'timestamp',    // Timestamp of the candle
    ];

    public function crypto()
    {
        return $this->belongsTo(Crypto::class);
    }
}
