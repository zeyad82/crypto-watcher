<?php

namespace App\Livewire;

use App\Models\Crypto;
use App\Models\VolumeData;
use Livewire\Component;

class Cryptos extends Component
{
    public function loadCryptoData()
    {
        $cryptos = Crypto::orderByDesc('volume24')
            ->with('latest1m', 'latest15m') // Load relationships
            ->take(120)
            ->get();

        return $cryptos->map(function ($crypto) {
            $latest1m = $crypto->latest1m;
            $latest15m = $crypto->latest15m;

            return [
                'symbol'           => $crypto->symbol,
                // 1m Timeframe
                'latest_price_1m'  => $latest1m->latest_price ?? 'N/A',
                'volume_1m'        => round($latest1m->last_volume * $latest1m->latest_price, 2) ?? 'N/A',
                'price_change_1m'  => $latest1m->price_change ?? 'N/A',
                'ema15_1m'         => $latest1m->price_ema_15 ?? 'N/A',
                'ema25_1m'         => $latest1m->price_ema_25 ?? 'N/A',
                'ema50_1m'         => $latest1m->price_ema_50 ?? 'N/A',
                'rsi_1m'           => $latest1m->meta['rsi'] ?? 'N/A',
                'adx_1m'           => $latest1m->meta['adx'] ?? 'N/A',
                '+di_1m'           => $latest1m->meta['+di'] ?? 'N/A',
                '-di_1m'           => $latest1m->meta['-di'] ?? 'N/A',
                // 15m Timeframe
                'latest_price_15m' => $latest15m->latest_price ?? 'N/A',
                'volume_15m'       => round($latest15m->last_volume * $latest15m->latest_price, 2) ?? 'N/A',
                'price_change_15m' => $latest15m->price_change ?? 'N/A',
                'ema15_15m'        => $latest15m->price_ema_15 ?? 'N/A',
                'ema25_15m'        => $latest15m->price_ema_25 ?? 'N/A',
                'ema50_15m'        => $latest15m->price_ema_50 ?? 'N/A',
                'rsi_15m'          => $latest15m->meta['rsi'] ?? 'N/A',
                'adx_15m'          => $latest15m->meta['adx'] ?? 'N/A',
                '+di_15m'          => $latest15m->meta['+di'] ?? 'N/A',
                '-di_15m'          => $latest15m->meta['-di'] ?? 'N/A',
            ];
        })->toArray();
    }

    public function render()
    {
        return view('livewire.cryptos', ['cryptoData' => $this->loadCryptoData()]); // Return the associated view
    }
}
