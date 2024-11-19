<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = ['crypto_id', 'trend', 'previous_trend', 'entry', 'stop_loss', 'tp1', 'tp2', 'tp3'];

    public function crypto()
    {
        return $this->belongsTo(Crypto::class);
    }

}
