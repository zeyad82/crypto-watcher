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
        'price_change',
        'timeframe',
        'vma_15',       // 15-period ma for volume
        'vma_25',       // 25-period ma for volume
        'vma_50',       // 50-period ma for volume
        'price_ema_15', // 15-period EMA for price
        'price_ema_25', // 25-period EMA for price
        'price_ema_50', // 50-period EMA for price
        'timestamp',    // Timestamp of the candle
        'meta'
    ];

    public function crypto()
    {
        return $this->belongsTo(Crypto::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
        ];
    }

    public function getNormalizedAtr()
    {
        return ($this->meta->get('atr') / $this->latest_price) * 100;
    }
}
