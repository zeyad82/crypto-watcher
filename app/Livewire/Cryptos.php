<?php

namespace App\Livewire;

use App\Models\Crypto;
use Livewire\Component;

class Cryptos extends Component
{
    public $sortColumn    = 'symbol'; // Default sort column
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
            ->with('latest1m', 'latest15m') // Eager load the required data
            ->take(40)
            ->get();

        $data = $cryptos->map(function ($crypto) {
            $latest1m  = $crypto->latest1m;
            $latest15m = $crypto->latest15m;

            return [
                'symbol'           => $crypto->symbol,
                'latest_price_1m'  => $latest1m->latest_price ?? 0,
                'rsi_1m'           => $latest1m->meta['rsi'] ?? 0,
                'rsi_15m'          => $latest15m->meta['rsi'] ?? 0,
                'volume_1m'        => round($latest1m->last_volume * $latest1m->latest_price, 2) ?? 0,
                'volume_15m'       => round($latest15m->last_volume * $latest1m->last_volume, 2) ?? 0,
                'price_change_1m'  => $latest1m->price_change ?? 0,
                'price_change_15m' => $latest15m->price_change ?? 0,
                'ema15_1m'         => $latest1m->price_ema_15 ?? 0,
                'ema25_1m'         => $latest1m->price_ema_25 ?? 0,
                'ema50_1m'         => $latest1m->price_ema_50 ?? 0,
                'ema15_15m'        => $latest15m->price_ema_15 ?? 0,
                'ema25_15m'        => $latest15m->price_ema_25 ?? 0,
                'ema50_15m'        => $latest15m->price_ema_50 ?? 0,
                'adx_1m'           => $latest1m->meta['adx'] ?? 0,
                '+di_1m'           => $latest1m->meta['+di'] ?? 0,
                '-di_1m'           => $latest1m->meta['-di'] ?? 0,
                'adx_15m'          => $latest15m->meta['adx'] ?? 0,
                '+di_15m'          => $latest15m->meta['+di'] ?? 0,
                '-di_15m'          => $latest15m->meta['-di'] ?? 0,
            ];
        });

        // Sort the data based on the selected column and direction
        return $data->sortBy(
            $this->sortColumn,
            SORT_REGULAR,
            $this->sortDirection === 'desc'
        )->values();
    }

    public function render()
    {
        return view('livewire.cryptos', ['cryptoData' => $this->loadCryptoData()]);
    }
}
