<?php

namespace App\Http\Controllers;

use App\Models\VolumeData;
use App\Services\Calculate;
use Illuminate\Support\Facades\Artisan;

class MainController extends Controller
{
    public function index()
    {
        $this->test();
    }

    public function test()
    {

        $recentData = VolumeData::where('crypto_id', 139)
            ->orderBy('timestamp', 'desc')
            ->where('timestamp', '<', '2024-11-21 15:10:00')
            ->take(120)
            ->get()->reverse()->values();

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $macd15mData = Calculate::MACD($closePrices);

        // Calculate 1-hour VW-MACD
        $hourlyClosePrices = array_chunk($closePrices, 4, false);
        $hourlyVolumes     = array_chunk($volumes, 4, false);

        $aggregatedClosePrices = array_map(fn($chunk) => array_sum($chunk) / count($chunk), $hourlyClosePrices);

        // dd($aggregatedClosePrices);
        $macd1hData = Calculate::MACD($aggregatedClosePrices);

        $result = [
            'macd_line'       => $macd15mData['macd_line'],
            'signal_line'     => $macd15mData['signal_line'],
            'histogram'       => $macd15mData['histogram'],
            'rsi'                => Calculate::RSI($closePrices),
            '1h_macd_line'    => $macd1hData['macd_line'],
            '1h_signal_line'  => $macd1hData['signal_line'],
            '1h_histogram'    => $macd1hData['histogram'],
        ];

        dd($recentData->toJson(), json_encode($result));

    }
}
