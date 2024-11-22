<?php

namespace App\Http\Controllers;

use App\Models\VolumeData;
use App\Services\Calculate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class MainController extends Controller
{
    public function index()
    {
        $this->test();
    }

    public function test()
    {

        $recentData = VolumeData::where('crypto_id', 330)
            ->orderBy('timestamp', 'desc')
            ->where('timestamp', '<', '2024-11-22 06:15:00')
            ->take(120)
            ->get()->reverse()->values();

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $macd15mData = Calculate::MACD($closePrices);


        $adxData = Calculate::ADX($highs, $lows, $closePrices, 14);

        $result = [
            'macd_line'       => $macd15mData['macd_line'],
            'signal_line'     => $macd15mData['signal_line'],
            'histogram'       => $macd15mData['histogram'],
            'rsi'                => Calculate::RSI($closePrices),
            'adx'                => $adxData['adx'],
            '+di'                => $adxData['+di'],
            '-di'                => $adxData['-di'],
        ];

        dump('data', json_encode($recentData));
        dd('result', json_encode($result));

    }
}
