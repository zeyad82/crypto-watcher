<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Ratchet\Client\Connector;
use React\EventLoop\Factory as LoopFactory;

class FetchVolumesWebSocket extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:fetch-volumes-websocket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch volume data for cryptos in real-time using Binance WebSocket.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Connecting to Binance WebSocket...');

        $loop      = LoopFactory::create();
        $connector = new Connector($loop);

        // Subscribe to multiple symbols
        $cryptoSymbols = Crypto::pluck('symbol')->toArray();
        $streams       = implode('/', array_map(fn($symbol) => strtolower($symbol) . '@kline_15m', $cryptoSymbols));

        $url = "wss://stream.binance.com:9443/stream?streams=$streams";

        $connector($url)->then(
            function ($connection) {
                $this->info('Connected to Binance WebSocket.');

                $connection->on('message', function ($message) {
                    $data = json_decode($message, true);

                    if (isset($data['data'])) {
                        $this->processKlineData($data['data']);
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

        return 0;
    }

    /**
     * Process kline (candlestick) data from the WebSocket.
     *
     * @param array $data
     */
    protected function processKlineData(array $data)
    {
        $symbol = strtoupper(explode('@', $data['s'])[0]); // Symbol from WebSocket data
        $crypto = Crypto::where('symbol', $symbol)->first();

        if (!$crypto) {
            $this->warn("Crypto not found: $symbol");
            return;
        }

        $kline = $data['k'];

        // Extract kline data
        $timestamp = Carbon::createFromTimestampMs($kline['t']);
        $open      = $kline['o'];
        $high      = $kline['h'];
        $low       = $kline['l'];
        $close     = $kline['c'];
        $volume    = $kline['v'];

        // Calculate metrics
        $existingData = VolumeData::where('crypto_id', $crypto->id)
            ->orderBy('timestamp', 'desc')
            ->take(50)
            ->get();

        $closePrices   = $existingData->pluck('close')->toArray();
        $closePrices[] = $close; // Add the latest close price

        $volumeData   = $existingData->pluck('last_volume')->toArray();
        $volumeData[] = $volume; // Add the latest volume

        $highs   = $existingData->pluck('high')->toArray();
        $highs[] = $high;

        $lows   = $existingData->pluck('low')->toArray();
        $lows[] = $low;

        $ema15    = $this->calculateEMA($closePrices, 15);
        $ema25    = $this->calculateEMA($closePrices, 25);
        $ema50    = $this->calculateEMA($closePrices, 50);
        $atr      = $this->calculateATR($highs, $lows, $closePrices);
        $macdData = $this->calculateMACD($closePrices);

        // Store in the database
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
                'vma_15'       => $this->calculateMA($volumeData, 15),
                'vma_25'       => $this->calculateMA($volumeData, 25),
                'vma_50'       => $this->calculateMA($volumeData, 50),
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

    /**
     * Calculate the EMA (same helper methods as the previous command).
     */
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

        $atr = array_sum(array_slice($tr, -$period)) / $period;
        return $atr;
    }

    protected function calculateMACD(array $closingPrices): array
    {
        // Calculate EMA arrays for 12-period and 26-period
        $ema12 = $this->calculateEMAs($closingPrices, 12); // Array of 12-period EMA values
        $ema26 = $this->calculateEMAs($closingPrices, 26); // Array of 26-period EMA values

        // Calculate MACD Line as the difference between EMA12 and EMA26 arrays
        $macdLine = array_map(fn($e12, $e26) => $e12 - $e26, $ema12, $ema26);

        // Calculate Signal Line (9-period EMA of the MACD Line)
        $signalLine = $this->calculateEMAs($macdLine, 9);

        // Calculate Histogram as the difference between MACD Line and Signal Line
        $histogram = array_map(fn($macd, $signal) => $macd - $signal, $macdLine, $signalLine);

        return [
            'macd_line' => end($macdLine), // Latest value of MACD Line
            'signal_line' => end($signalLine), // Latest value of Signal Line
            'histogram' => end($histogram), // Latest value of Histogram
        ];
    }

    /**
     * Calculate Exponential Moving Average (EMA) as an array.
     *
     * @param array $values Array of closing prices.
     * @param int $period EMA period (e.g., 12, 26, 9).
     * @return array Array of EMA values.
     */
    protected function calculateEMAs(array $values, int $period): array
    {
        $k = 2 / ($period + 1); // Smoothing factor
        $ema = [];
        $ema[0] = $values[0]; // Initialize the first EMA value with the first data point

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $k) + ($ema[$i - 1] * (1 - $k)); // EMA formula
        }

        return $ema;
    }
}
