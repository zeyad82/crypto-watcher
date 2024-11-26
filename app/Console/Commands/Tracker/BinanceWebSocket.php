<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use App\Services\Calculate;
use Cache;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Ratchet\Client\Connector;
use React\EventLoop\Loop;

class BinanceWebSocket extends Command
{
    protected $signature   = 'tracker:binance-websocket {timeframe?}';
    protected $description = 'Fetch volume data in real-time using Binance WebSocket.';

    protected $timeframe;

    protected $cryptos = [];

    public function handle()
    {
        $this->timeframe = $this->argument('timeframe') ?? '15m';

        $this->info('Connecting to Binance WebSocket...');

        // Fetch and cache all crypto symbols and their IDs in a normalized format
        $this->cryptos = Crypto::pluck('id', 'symbol')->mapWithKeys(function ($id, $symbol) {
            return [strtoupper(str_replace('/', '', $symbol)) => $id];
        })->toArray();

        if (empty($this->cryptos)) {
            $this->warn('No cryptos available for WebSocket connection. Exiting.');
            return 0;
        }

        // Prepare streams
        $cryptoSymbols = array_keys($this->cryptos);
        $streams       = implode('/', array_map(
            fn($symbol) => strtolower($symbol) . '@kline_' . $this->timeframe,
            $cryptoSymbols
        ));

        $url = "wss://stream.binance.com:9443/stream?streams=$streams";

        $loop      = Loop::get();
        $connector = new Connector($loop);

        // Buffer for 1m timeframe
        $buffer = [];

        $connector($url)->then(
            function ($connection) use ($loop, &$buffer) {
                $this->info('Connected to Binance WebSocket.');

                // Collect messages in the buffer
                $connection->on('message', function ($message) use (&$buffer) {
                    $data = json_decode($message, true);

                    if (isset($data['data'])) {
                        $buffer[] = $data['data'];
                    }
                });

                // Add a periodic timer to process the buffer every 10 seconds
                $loop->addPeriodicTimer(10, function () use (&$buffer) {
                    if (!empty($buffer)) {
                        $this->processBufferedData($buffer);
                        $buffer = []; // Clear the buffer after processing
                    }
                });

                $connection->on('close', function () {
                    $this->warn('WebSocket connection closed.');
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
        // Normalize WebSocket symbol format to match cached data
        $symbol = strtoupper($data['s']); // WebSocket sends uppercase symbols like BTCUSDT

        // Get the crypto ID from the cached cryptos property
        $cryptoId = $this->cryptos[$symbol] ?? null;

        if (!$cryptoId) {
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

        $recentData = Cache::remember($this->timeframe . $cryptoId . $timestamp, $this->cacheTime(), function () use ($cryptoId, $timestamp) {
            return VolumeData::where('crypto_id', $cryptoId)
                ->where('timeframe', $this->timeframe)
                ->orderBy('timestamp', 'desc')
                ->where('timestamp', '!=', $timestamp)
                ->take(49)
                ->get()->reverse();

        });

        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();

        $highs[]       = $high;
        $lows[]        = $low;
        $closePrices[] = $close;
        $volumes[]     = $volume;

        $macdData = Calculate::MACD($closePrices);

        $previousClose = $recentData->last()?->close ?? $close;
        $priceChange   = $previousClose != 0
        ? (($close - $previousClose) / $previousClose) * 100
        : 0;

        $previousHistogram = $recentData->last()?->meta['histogram'] ?? 0;

        // Calculate ADX, +DI, and -DI
        $adxData = Calculate::ADX($highs, $lows, $closePrices, 14);

        VolumeData::updateOrCreate(
            [
                'crypto_id' => $cryptoId,
                'timestamp' => $timestamp,
                'timeframe' => $this->timeframe,
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
                    'macd_line'          => $macdData['macd_line'],
                    'signal_line'        => $macdData['signal_line'],
                    'histogram'          => $macdData['histogram'],
                    'previous_histogram' => $previousHistogram,
                    'rsi'                => Calculate::RSI($closePrices),
                    'adx'                => $adxData['adx'],
                    '+di'                => $adxData['+di'],
                    '-di'                => $adxData['-di'],

                ],
            ]
        );
    }

    protected function processBufferedData(array $buffer)
    {
        foreach ($buffer as $data) {
            $this->processKlineData($data);
        }
    }

    protected function cacheTime()
    {
        $times = [
            '1m'  => 61,
            '15m' => 15 * 60 + 1,
            '1h'  => 60 * 60 + 1,
        ];

        return $times[$this->timeframe];
    }

}
