<?php

namespace App\Livewire;

use App\Models\Crypto;
use App\Models\VolumeData;
use Livewire\Component;

class Cryptos extends Component
{
    public $sortColumn    = 'rsi_1m'; // Default sort column
    public $sortDirection = 'asc'; // Default sort direction

    public function sortBy($column)
    {
        if ($this->sortColumn === $column) {
            // Toggle the sorting direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // Set a new column for sorting and reset direction
            $this->sortColumn    = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function loadCryptoData()
    {
        $cryptos = Crypto::orderByDesc('volume24')
            ->with('latest1m', 'latest15m', 'latest1h', 'latest4h') // Eager load the required data
        // ->take(200)
            ->get();
        // ->filter(function($crypto) {
        //     return $crypto->latest1m->latest_price < $crypto->latest4h->price_ema_50;
        // });

        $data = $cryptos->map(function ($crypto) {
            $latest1m  = $crypto->latest1m;
            $latest15m = $crypto->latest15m;
            $latest1h  = $crypto->latest1h;
            $latest4h  = $crypto->latest4h;

            $rawVolume1m  = $latest1m->last_volume * $latest1m->latest_price ?? 0;
            $rawVolume15m = $latest15m->last_volume * $latest1m->latest_price ?? 0;
            $rawVolume1h  = $latest1h->last_volume * $latest1m->latest_price ?? 0;
            $rawVolume24h = $crypto->volume24 ?? 0;

            $recentHigh  = $latest4h->meta['recent_high'] ?? 0;
            $recentLow   = $latest4h->meta['recent_low'] ?? 0;
            $latestPrice = $latest1m->latest_price ?? 0;

            $percentUpFromLow    = $recentLow ? (($latestPrice - $recentLow) / $recentLow) * 100 : 0;
            $percentDownFromHigh = $recentHigh ? (($recentHigh - $latestPrice) / $recentHigh) * 100 : 0;

            return [
                'symbol'           => $crypto->symbol,
                'latest_price_1m'  => $latest1m->latest_price ?? 0,
                'rsi_1m'           => $latest1m->meta['rsi'] ?? 0,
                'rsi_15m'          => $latest15m->meta['rsi'] ?? 0,
                'rsi_1h'           => $latest1h->meta['rsi'] ?? 0,
                'rsi_4h'           => $latest4h->meta['rsi'] ?? 0,

                'up%'              => round($percentUpFromLow, 2),
                'down%'            => round($percentDownFromHigh, 2),
                'entry_score'      => $latest4h->meta['entry_score'] ?? 0,

                'raw_volume_1m'    => $rawVolume1m,
                'volume_1m'        => $this->formatNumber($rawVolume1m),

                'raw_volume_15m'   => $rawVolume15m,
                'volume_15m'       => $this->formatNumber($rawVolume15m),

                'raw_volume_1h'    => $rawVolume1h,
                'volume_1h'        => $this->formatNumber($rawVolume1h),

                'raw_volume_24h'   => $rawVolume24h,
                'volume_24h'       => $this->formatNumber($rawVolume24h),

                'price_change_1m'  => $latest1m->price_change ?? 0,
                'price_change_15m' => $latest15m->price_change ?? 0,
                'price_change_1h'  => $latest1h->price_change ?? 0,

                '15m_ema_trend'    => $this->getTrend($latest15m),
                '1h_ema_trend'     => $this->getTrend($latest1h),
                '4h_ema_trend'     => $this->getTrend($latest4h),
            ];
        });

        // Define the columns that should use raw values for sorting
        $columnsWithRawValues = ['volume_1m', 'volume_15m', 'volume_1h', 'volume_24h'];

        // Determine if the column being sorted has a raw counterpart
        $sortColumn = in_array($this->sortColumn, $columnsWithRawValues)
        ? "raw_{$this->sortColumn}"// Use raw value for sorting
        : $this->sortColumn; // Use original column

        // Sort the data based on the selected column and direction
        return $data->sortBy(
            $sortColumn,
            SORT_REGULAR,
            $this->sortDirection === 'desc'
        )->values();
    }

    public function render()
    {
        return view('livewire.cryptos', ['cryptoData' => $this->loadCryptoData()]);
    }

    private function formatNumber($number)
    {
        if ($number >= 1_000_000_000) {
            return round($number / 1_000_000_000, 2) . 'B';
        } elseif ($number >= 1_000_000) {
            return round($number / 1_000_000, 2) . 'M';
        } elseif ($number >= 1_000) {
            return round($number / 1_000, 2) . 'K';
        }
        return round($number, 2);
    }

    protected function getTrend(VolumeData $data)
    {
        if (!$data->price_ema_15 || !$data->price_ema_25) {
            return 'empty';
        }

        if ($data->price_ema_15 > $data->price_ema_25) {
            return 'bullish';
        }

        if ($data->price_ema_15 < $data->price_ema_25) {
            return 'bearish';
        }
    }

    public static function columns()
    {
        return [
            'symbol' => 'Symbol',
            'latest_price_1m' => 'Price',
            'rsi_1m' => '1m RSI',
            'rsi_15m' => '15m RSI',
            'rsi_1h' => '1h RSI',
            'rsi_4h' => '4h RSI',
            'up%' => 'Up %',
            'down%' => 'Down %',
            'entry_score' => 'Entry Score',
            'volume_1m' => '1m Volume',
            'volume_15m' => '5m Volume',
            'volume_1h' => '1h Volume',
            'volume_24h' => '24 Volume',
            'price_change_1m' => '1m Price Change (%)',
            'price_change_15m' => '5m Price Change (%)',
            'price_change_1h' => '1h Price Change (%)',
            '15m_ema_trend' => '15m EMA Trend',
            '1h_ema_trend' => '1h EMA Trend',
            '4h_ema_trend' => '4h EMA Trend',
        ];
    }
}
