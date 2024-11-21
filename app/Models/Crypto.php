<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crypto extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'base_asset', 'quote_asset', 'volume24', 'last_fetched', 'last_trend', 'last_volume_alert'];

    public function volumeData()
    {
        return $this->hasMany(VolumeData::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_fetched'      => 'date:Y-m-d H:i:s',
            'last_volume_alert' => 'date:Y-m-d H:i:s',
        ];
    }

}
