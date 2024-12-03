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
    protected $signature   = 'tracker:binance-websocket {timeframe?} {--once}';
    protected $description = 'Fetch volume data in real-time using Binance WebSocket.';

    protected $timeframe;
    protected $runOnce = false;

    protected $cryptos = [];
    protected $processedCryptos = [];

    public function handle()
    {
        $this->timeframe = $this->argument('timeframe') ?? '15m';
        $this->runOnce = $this->option('once');

        $this->info('Connecting to Binance WebSocket...');

        $this->cryptos = Crypto::pluck('id', 'symbol')->mapWithKeys(function ($id, $symbol) {
            return [strtoupper(str_replace('/', '', $symbol)) => $id];
        })->toArray();

        if (empty($this->cryptos)) {
            $this->warn('No cryptos available for WebSocket connection. Exiting.');
            return 0;
        }

        $cryptoSymbols = array_keys($this->cryptos);
        $streams       = implode('/', array_map(
            fn($symbol) => strtolower($symbol) . '@kline_' . $this->timeframe,
            $cryptoSymbols
        ));

        $url = "wss://stream.binance.com:9443/stream?streams=$streams";

        $loop      = Loop::get();
        $connector = new Connector($loop);

        $buffer = [];

        $connector($url)->then(
            function ($connection) use ($loop, &$buffer) {
                $this->info('Connected to Binance WebSocket.');

                $connection->on('message', function ($message) use (&$buffer) {
                    $data = json_decode($message, true);
                    if (isset($data['data'])) {
                        $buffer[] = $data['data'];
                    }
                });

                $loop->addPeriodicTimer(10, function () use (&$buffer, $connection, $loop) {
                    if (!empty($buffer)) {
                        $this->processBufferedData($buffer);
                        $buffer = [];
                    }

                    if ($this->runOnce && count($this->processedCryptos) === count($this->cryptos)) {
                        $connection->close();
                        $loop->stop();
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
        $symbol   = strtoupper($data['s']);
        $cryptoId = $this->cryptos[$symbol] ?? null;

        if (!$cryptoId) {
            $this->warn("Crypto not found: $symbol");
            return;
        }

        if (!in_array($cryptoId, $this->processedCryptos)) {
            $this->processedCryptos[] = $cryptoId;
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

        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();
        $closePrices = $recentData->pluck('close')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();
        $last        = optional($recentData->last());
        $highs[]       = $high;
        $lows[]        = $low;
        $closePrices[] = $close;
        $volumes[]     = $volume;

        $recentHigh = max(array_merge($highs, array_filter([$last->meta['recent_high'], $high])));
        $recentLow  = min(array_merge($lows, array_filter([$last->meta['recent_low'], $low])));

        $fibonacciLevels = $this->calculateFibonacciLevels($recentHigh, $recentLow);
        $entryScore      = $this->calculateEntryScore($close, $recentHigh, $recentLow, $fibonacciLevels);

        $macdData      = Calculate::MACD($closePrices);
        $adxData       = Calculate::ADX($highs, $lows, $closePrices, 14);
        $atr           = Calculate::ATR($highs, $lows, $closePrices);
        $rsi           = Calculate::RSI($closePrices);
        $previousClose = $last->close ?? $close;
        $priceChange   = $previousClose != 0
        ? (($close - $previousClose) / $previousClose) * 100
        : 0;
        $previousHistogram = $last->meta['histogram'] ?? 0;

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
                    'recent_high'        => $recentHigh,
                    'recent_low'         => $recentLow,
                    'fibonacci_levels'   => $fibonacciLevels,
                    'entry_score'        => $entryScore,
                    'atr'                => $atr,
                    'macd_line'          => $macdData['macd_line'],
                    'signal_line'        => $macdData['signal_line'],
                    'histogram'          => $macdData['histogram'],
                    'previous_histogram' => $previousHistogram,
                    'rsi'                => $rsi,
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

    protected function calculateFibonacciLevels($high, $low)
    {
        $difference = $high - $low;

        return [
            'level_0'    => $high,
            'level_38_2' => $high - $difference * 0.382,
            'level_50'   => $high - $difference * 0.5,
            'level_61_8' => $high - $difference * 0.618,
            'level_100'  => $low,
        ];
    }

    protected function calculateEntryScore($currentPrice, $recentHigh, $recentLow, $fibonacciLevels)
    {
        $range = $recentHigh - $recentLow;

        if ($range == 0) {
            // If range is zero, return a default score
            return $currentPrice == $recentHigh ? 100 : 0;
        }

        $lowProximityScore = max(0, min(100, 100 * (1 - ($currentPrice - $recentLow) / $range)));
        $fibScores         = [
            'level_61_8' => 60,
            'level_50'   => 40,
            'level_38_2' => 20,
        ];

        $fibonacciScore = 0;
        foreach ($fibScores as $level => $weight) {
            $distance        = abs($currentPrice - $fibonacciLevels[$level]);
            $rangeProportion = $distance / $range;
            $fibonacciScore += max(0, $weight * (1 - $rangeProportion));
        }

        $highProximityScore = max(0, min(100, 100 * ($recentHigh - $currentPrice) / $range));

        return round(0.5 * $lowProximityScore + 0.4 * $fibonacciScore + 0.1 * $highProximityScore, 2);
    }

    protected function cacheTime()
    {
        $times = [
            '1m'  => 61,
            '15m' => 15 * 60 + 1,
            '1h'  => 60 * 60 + 1,
        ];

        return $times[$this->timeframe] ?? 60;
    }
}
