<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use ccxt\binance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchVolumes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:fetch-volumes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch volume data for 500 cryptos at a time based on the last_fetched attribute.';

    /**
     * Binance API instance.
     *
     * @var binance
     */
    protected $exchange;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize Binance API
        $this->exchange = new binance([
            'rateLimit'       => 1200,
            'enableRateLimit' => true,
        ]);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $batchSize = 500; // Fixed batch size
        $this->info("Fetching volume data for {$batchSize} cryptos...");

        try {
            // Get the first 500 cryptos sorted by last_fetched (oldest first)
            $cryptos = Crypto::orderBy('last_fetched', 'asc')
                ->take($batchSize)
                ->get();

            if ($cryptos->isEmpty()) {
                $this->info('No cryptos to fetch.');
                return 0;
            }

            // Initialize progress bar
            $progressBar = $this->output->createProgressBar($cryptos->count());
            $progressBar->start();

            foreach ($cryptos as $crypto) {
                try {
                    // Fetch the last 100 candles for the trading pair
                    $ohlcv = $this->exchange->fetch_ohlcv($crypto->symbol, '5m', null, 100);
                    if (count($ohlcv) < 100) {
                        Log::warning("Insufficient candle data for {$crypto->symbol}");
                        $progressBar->advance();
                        continue;
                    }

                    // Extract data for the most recent candle
                    $lastCandle = $ohlcv[count($ohlcv) - 1];
                    $timestamp  = date('Y-m-d H:i:s', $lastCandle[0] / 1000);
                    $open       = $lastCandle[1];
                    $high       = $lastCandle[2];
                    $low        = $lastCandle[3];
                    $close      = $lastCandle[4];
                    $volume     = $lastCandle[5];

                    // Extract data arrays for EMA calculations
                    $volumes     = array_column($ohlcv, 5);
                    $closePrices = array_column($ohlcv, 4);

                    // Calculate EMAs for volume
                    $volumeEma7   = $this->calculateEMA($volumes, 7);
                    $volumeEma15  = $this->calculateEMA($volumes, 15);
                    $volumeEma25  = $this->calculateEMA($volumes, 25);
                    $volumeEma50  = $this->calculateEMA($volumes, 50);
                    $volumeEma100 = $this->calculateEMA($volumes, 100);

                    // Calculate EMAs for price
                    $priceEma7   = $this->calculateEMA($closePrices, 7);
                    $priceEma15  = $this->calculateEMA($closePrices, 15);
                    $priceEma25  = $this->calculateEMA($closePrices, 25);
                    $priceEma50  = $this->calculateEMA($closePrices, 50);
                    $priceEma100 = $this->calculateEMA($closePrices, 100);

                    // Store data in the database
                    VolumeData::create([
                        'crypto_id'     => $crypto->id,
                        'open'          => $open,
                        'high'          => $high,
                        'low'           => $low,
                        'close'         => $close,
                        'last_volume'   => $volume,
                        'latest_price'  => $close,
                        'ema_7'         => $volumeEma7,
                        'ema_15'        => $volumeEma15,
                        'ema_25'        => $volumeEma25,
                        'ema_50'        => $volumeEma50,
                        'ema_100'       => $volumeEma100,
                        'price_ema_7'   => $priceEma7,
                        'price_ema_15'  => $priceEma15,
                        'price_ema_25'  => $priceEma25,
                        'price_ema_50'  => $priceEma50,
                        'price_ema_100' => $priceEma100,
                        'timestamp'     => $timestamp,
                    ]);

                    // Update last_fetched timestamp for the crypto
                    $crypto->update(['last_fetched' => Carbon::now()]);
                    $progressBar->advance();
                } catch (\Exception $e) {
                    Log::error("Error fetching data for {$crypto->symbol}: " . $e->getMessage());
                    $this->warn("Failed to fetch data for {$crypto->symbol}.");
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $this->info("\nFinished fetching volume data for the batch.");
        } catch (\Exception $e) {
            Log::error('Error fetching volumes: ' . $e->getMessage());
            $this->error('Failed to fetch volumes. Check the logs for details.');
        }

        return 0;
    }

    /**
     * Calculate the Exponential Moving Average (EMA).
     *
     * @param array $values Array of data points (volume or price).
     * @param int $period EMA period (e.g., 7, 15, 25).
     * @return float The calculated EMA.
     */
    protected function calculateEMA(array $values, int $period): float
    {
        $k   = 2 / ($period + 1); // Smoothing factor
        $ema = $values[0]; // Initialize EMA with the first data point

        foreach ($values as $index => $value) {
            if ($index == 0) {
                continue;
            }
            // Skip the first value
            $ema = ($value * $k) + ($ema * (1 - $k)); // EMA formula
        }

        return $ema;
    }
}
