<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crypto extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'base_asset', 'quote_asset', 'last_fetched', 'last_trend', 'last_volume_alert'];

    public function volumeData()
    {
        return $this->hasMany(VolumeData::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_volume_alert' => 'date',
            'last_volume_alert' => 'date',
        ];
    }

}
