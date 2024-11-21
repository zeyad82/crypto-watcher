<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use App\Services\Calculate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

class FetchVolumesWebSocket extends Command
{
    protected $signature   = 'tracker:fetch-volumes-websocket';
    protected $description = 'Fetch volume data in real-time using Binance WebSocket.';

    public function handle()
    {
        $this->info('Connecting to Binance WebSocket...');
        $loop      = Loop::get();
        $connector = new Connector($loop);

        $cryptoSymbols = Crypto::pluck('symbol')->toArray();

        $streams       = implode('/', array_map(fn($symbol) => str_replace('/', '', strtolower($symbol)) . '@kline_15m', $cryptoSymbols));

        $url           = "wss://stream.binance.com:9443/stream?streams=$streams";

        $connector($url)->then(
            function ($connection) use ($loop) {
                $this->info('Connected to Binance WebSocket.');

                $connection->on('message', function ($message) {
                    $data = json_decode($message, true);

                    if (isset($data['data'])) {
                        $this->processKlineData($data['data']);
                    }
                });

                $connection->on('close', function () {
                    $this->warn('WebSocket connection closed. Reconnecting...');
                    $this->handle();
                });
            },
            function ($error) {
                $this->error('Could not connect to Binance WebSocket: ' . $error->getMessage());
            }
        );

        $loop->run();
    }

    protected function processKlineData(array $data)
    {
        $symbol = strtoupper(explode('@', $data['s'])[0]);

        $crypto = Crypto::where('base_asset', str_replace('USDT', '', $symbol))->first();

        if (!$crypto) {
            $this->warn("Crypto not found: $symbol");
            return;
        }

        $kline = $data['k'];

        $timestamp = Carbon::createFromTimestampMs($kline['t']);
        $open      = $kline['o'];
        $high      = $kline['h'];
        $low       = $kline['l'];
        $close     = $kline['c'];
        $volume    = $kline['v'];

        $recentData = VolumeData::where('crypto_id', $crypto->id)
            ->orderBy('timestamp', 'asc')
            ->take(119)
            ->get();

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $highs[]       = $high;
        $lows[]        = $low;
        $closePrices[] = $close;
        $volumes[]     = $volume;


        $vwMacd15mData = Calculate::VW_MACD($closePrices, $volumes);

        // Fetch the most recent histogram if it exists
        $previousHistogram = $recentData->last()->meta['vw_histogram'] ?? 0;
        
        // Calculate 1-hour VW-MACD
        $hourlyClosePrices = array_chunk($closePrices, 4, false);
        $hourlyVolumes     = array_chunk($volumes, 4, false);

        $aggregatedClosePrices = array_map(fn($chunk) => array_sum($chunk) / count($chunk), $hourlyClosePrices);
        $aggregatedVolumes     = array_map(fn($chunk) => array_sum($chunk), $hourlyVolumes);

        $vwMacd1hData = Calculate::VW_MACD($aggregatedClosePrices, $aggregatedVolumes);

        VolumeData::updateOrCreate(
            [
                'crypto_id' => $crypto->id,
                'timestamp' => $timestamp,
            ],
            [
                'open'         => $open,
                'high'         => $high,
                'low'          => $low,
                'close'        => $close,
                'last_volume'  => $volume,
                'latest_price' => $close,
                'vma_15'       => Calculate::MA($volumes, 15),
                'vma_25'       => Calculate::MA($volumes, 25),
                'vma_50'       => Calculate::MA($volumes, 50),
                'price_ema_15' => Calculate::EMA($closePrices, 15),
                'price_ema_25' => Calculate::EMA($closePrices, 25),
                'price_ema_50' => Calculate::EMA($closePrices, 50),
                'meta'         => [
                    'atr'            => Calculate::ATR($highs, $lows, $closePrices),
                    'vw_macd_line'   => $vwMacd15mData['macd_line'],
                    'vw_signal_line' => $vwMacd15mData['signal_line'],
                    'vw_histogram'   => $vwMacd15mData['histogram'],
                    'previous_histogram' => $previousHistogram,
                    'rsi'            => Calculate::RSI($closePrices),
                    '1h_vw_macd_line'   => $vwMacd1hData['macd_line'],
                    '1h_vw_signal_line' => $vwMacd1hData['signal_line'],
                    '1h_vw_histogram'   => $vwMacd1hData['histogram'],
                ],
            ]
        );

        $this->info("Processed kline for {$crypto->symbol} at {$timestamp}");
    }
}
