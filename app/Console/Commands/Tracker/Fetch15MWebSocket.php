<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use App\Services\Calculate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

class Fetch15MWebSocket extends Command
{
    protected $signature   = 'tracker:fetch-15m-websocket';
    protected $description = 'Fetch volume data in real-time using Binance WebSocket.';

    public function handle()
    {
        $this->info('Connecting to Binance WebSocket...');

        // Fetch top 120 cryptos by volume
        $cryptoSymbols = Crypto::orderByDesc('volume24')
            ->get()
            ->pluck('symbol')->toArray();

        if (empty($cryptoSymbols)) {
            $this->warn('No cryptos available for WebSocket connection. Exiting.');
            return 0;
        }

        $streams = implode('/', array_map(fn($symbol) => str_replace('/', '', strtolower($symbol)) . '@kline_15m', $cryptoSymbols));
        $url     = "wss://stream.binance.com:9443/stream?streams=$streams";

        $loop      = Loop::get();
        $connector = new Connector($loop);

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
            ->orderBy('timestamp', 'desc')
            ->take(49)
            ->get()->reverse()->values();

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $highs[]       = $high;
        $lows[]        = $low;
        $closePrices[] = $close;
        $volumes[]     = $volume;

        $macd15mData = Calculate::MACD($closePrices);

        $previousClose = $recentData->last()?->close ?? $close; 
        $priceChange = $previousClose != 0 
            ? (($close - $previousClose) / $previousClose) * 100 
            : 0;

        $previousHistogram = $recentData->last()?->meta['histogram'] ?? 0;

        // Calculate ADX, +DI, and -DI
        $adxData = Calculate::ADX($highs, $lows, $closePrices, 14);

        VolumeData::updateOrCreate(
            [
                'crypto_id' => $crypto->id,
                'timestamp' => $timestamp,
                'timeframe' => '15m' 
            ],
            [
                'open'         => $open,
                'high'         => $high,
                'low'          => $low,
                'close'        => $close,
                'last_volume'  => $volume,
                'latest_price' => $close,
                'price_change' => round($priceChange, 2),
                'vma_15'       => Calculate::MA($volumes, 15),
                'vma_25'       => Calculate::MA($volumes, 25),
                'vma_50'       => Calculate::MA($volumes, 50),
                'price_ema_15' => Calculate::EMA($closePrices, 15),
                'price_ema_25' => Calculate::EMA($closePrices, 25),
                'price_ema_50' => Calculate::EMA($closePrices, 50),
                'meta'         => [
                    'atr'                => Calculate::ATR($highs, $lows, $closePrices),
                    'macd_line'          => $macd15mData['macd_line'],
                    'signal_line'        => $macd15mData['signal_line'],
                    'histogram'          => $macd15mData['histogram'],
                    'previous_histogram' => $previousHistogram,
                    'rsi'                => Calculate::RSI($closePrices),
                    'adx'                => $adxData['adx'],
                    '+di'                => $adxData['+di'],
                    '-di'                => $adxData['-di'],

                ],
            ]
        );

        $this->info("Processed kline for {$crypto->symbol} at {$timestamp}");
    }
}
