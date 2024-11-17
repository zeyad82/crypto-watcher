<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crypto extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'base_asset', 'quote_asset'];

    public function volumeData()
    {
        return $this->hasMany(VolumeData::class);
    }

}
