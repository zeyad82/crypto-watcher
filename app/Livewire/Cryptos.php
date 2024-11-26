<?php

namespace App\Livewire;

use App\Models\Crypto;
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
            ->with('latest1m', 'latest15m', 'latest1h') // Eager load the required data
            ->take(80)
            ->get();

        $data = $cryptos->map(function ($crypto) {
            $latest1m  = $crypto->latest1m;
            $latest15m = $crypto->latest15m;
            $latest1h = $crypto->latest1h;

            $rawVolume1m  = $latest1m->last_volume * $latest1m->latest_price ?? 0;
            $rawVolume15m = $latest15m->last_volume * $latest1m->latest_price ?? 0;
            $rawVolume1h  = $latest1h->last_volume * $latest1m->latest_price ?? 0;


            return [
                'symbol'           => $crypto->symbol,
                'latest_price_1m'  => $latest1m->latest_price ?? 0,
                'rsi_1m'           => $latest1m->meta['rsi'] ?? 0,
                'rsi_15m'          => $latest15m->meta['rsi'] ?? 0,
                'rsi_1h'          => $latest1h->meta['rsi'] ?? 0,
                
                'raw_volume_1m'    => $rawVolume1m,
                'volume_1m'        => $this->formatNumber($rawVolume1m),

                'raw_volume_15m'   => $rawVolume15m,
                'volume_15m'       => $this->formatNumber($rawVolume15m),

                'raw_volume_1h'    => $rawVolume1h,
                'volume_1h'        => $this->formatNumber($rawVolume1h),
                
                'price_change_1m'  => $latest1m->price_change ?? 0,
                'price_change_15m' => $latest15m->price_change ?? 0,
                'price_change_1h' => $latest1h->price_change ?? 0,

                'ema15_15m'        => $latest15m->price_ema_15 ?? 0,
                'ema25_15m'        => $latest15m->price_ema_25 ?? 0,
                'ema50_15m'        => $latest15m->price_ema_50 ?? 0,
                
                'ema15_1h'        => $latest1h->price_ema_15 ?? 0,
                'ema25_1h'        => $latest1h->price_ema_25 ?? 0,
                'ema50_1h'        => $latest1h->price_ema_50 ?? 0,
            ];
        });

        // Define the columns that should use raw values for sorting
        $columnsWithRawValues = ['volume_1m', 'volume_15m', 'volume_1h'];

        // Determine if the column being sorted has a raw counterpart
        $sortColumn = in_array($this->sortColumn, $columnsWithRawValues)
            ? "raw_{$this->sortColumn}" // Use raw value for sorting
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
        return $number;
    }
}
