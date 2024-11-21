<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = ['crypto_id', 'volume_id', 'trend', 'previous_trend', 'entry', 'stop_loss', 'tp1', 'tp2', 'tp3', 'result', 'status'];

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
            'result'      => 'integer',
        ];
    }
}
