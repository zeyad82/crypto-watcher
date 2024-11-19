<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
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
        $streams       = implode('/', array_map(fn($symbol) => strtolower($symbol) . '@kline_15m', $cryptoSymbols));
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
        $symbol = strtoupper(explode('@', $data['s'])[0]); // Extract symbol from WebSocket data
        $crypto = Crypto::where('symbol', $symbol)->first();

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
            ->take(50)
            ->get();

        $closePrices = $recentData->pluck('close')->toArray();
        $highs       = $recentData->pluck('high')->toArray();
        $lows        = $recentData->pluck('low')->toArray();
        $volumes     = $recentData->pluck('last_volume')->toArray();

        $closePrices[] = $close;
        $highs[]       = $high;
        $lows[]        = $low;
        $volumes[]     = $volume;

        $ema15    = $this->calculateEMA($closePrices, 15);
        $ema25    = $this->calculateEMA($closePrices, 25);
        $ema50    = $this->calculateEMA($closePrices, 50);
        $atr      = $this->calculateATR($highs, $lows, $closePrices);
        $macdData = $this->calculateMACD($closePrices);

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
                'vma_15'       => $this->calculateMA($volumes, 15),
                'vma_25'       => $this->calculateMA($volumes, 25),
                'vma_50'       => $this->calculateMA($volumes, 50),
                'price_ema_15' => $ema15,
                'price_ema_25' => $ema25,
                'price_ema_50' => $ema50,
                'meta'         => [
                    'atr'         => $atr,
                    'macd_line'   => $macdData['macd_line'],
                    'signal_line' => $macdData['signal_line'],
                    'histogram'   => $macdData['histogram'],
                ],
            ]
        );

        $this->info("Processed kline for {$crypto->symbol} at {$timestamp}");
    }

    protected function calculateEMA(array $values, int $period): float
    {
        $k   = 2 / ($period + 1);
        $ema = $values[0];

        foreach ($values as $index => $value) {
            if ($index === 0) {
                continue;
            }
            $ema = ($value * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    protected function calculateMA(array $data, int $period): float
    {
        if (count($data) < $period) {
            return 0;
        }

        $subset = array_slice($data, -$period);
        return array_sum($subset) / $period;
    }

    protected function calculateATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        $tr = [];
        for ($i = 1; $i < count($highs); $i++) {
            $tr[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }

        return array_sum(array_slice($tr, -$period)) / $period;
    }

    protected function calculateMACD(array $closingPrices): array
    {
        $ema12 = $this->calculateEMAs($closingPrices, 12);
        $ema26 = $this->calculateEMAs($closingPrices, 26);

        $macdLine   = array_map(fn($e12, $e26) => $e12 - $e26, $ema12, $ema26);
        $signalLine = $this->calculateEMAs($macdLine, 9);
        $histogram  = array_map(fn($macd, $signal) => $macd - $signal, $macdLine, $signalLine);

        return [
            'macd_line'   => end($macdLine),
            'signal_line' => end($signalLine),
            'histogram'   => end($histogram),
        ];
    }

    protected function calculateEMAs(array $values, int $period): array
    {
        $k      = 2 / ($period + 1);
        $ema    = [];
        $ema[0] = $values[0];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $k) + ($ema[$i - 1] * (1 - $k));
        }

        return $ema;
    }
}
