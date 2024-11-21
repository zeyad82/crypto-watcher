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

        $recentData = VolumeData::where('crypto_id', 139)
            ->orderBy('timestamp', 'desc')
            ->where('timestamp', '<', '2024-11-21 15:20:00')
            ->take(120)
            ->get()->reverse()->values();

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $macd15mData = Calculate::MACD($closePrices);

        // Calculate 1-hour
        $hourlyClosePrices = [];
        foreach ($recentData as $row) {
            // Get the timestamp of the row
            $rowTimestamp = Carbon::parse($row['timestamp']);

            // Check if it's the last 15m period in an hour (e.g., 09:45, 10:45, ...)
            if ($rowTimestamp->minute === 45) {
                $hourlyClosePrices[] = (float)$row['close'];
            }
        }

        $macd1hData = Calculate::MACD($hourlyClosePrices);

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
