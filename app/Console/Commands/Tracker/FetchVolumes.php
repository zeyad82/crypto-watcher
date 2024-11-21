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
    protected $signature   = 'tracker:fetch-volumes {cryptos?}';
    protected $description = 'Fetch volume data and store 120 candles as history for future WebSocket use.';

    protected $exchange;

    public function __construct()
    {
        parent::__construct();

        // Initialize Binance API
        $this->exchange = new binance([
            'rateLimit'       => 1200,
            'enableRateLimit' => true,
        ]);
    }

    public function handle()
    {
        $batchSize = 400; // Fetch data for 400 cryptos in a batch
        $this->info("Fetching volume data for {$batchSize} cryptos...");

        try {
            $cryptos = Crypto::orderBy('last_fetched', 'asc')
                ->when($this->argument('cryptos'), function($cryptos) {
                    return $cryptos->whereIn('id', $this->argument('cryptos'));
                })
                ->take($batchSize)
                ->get();

            if ($cryptos->isEmpty()) {
                $this->info('No cryptos to fetch.');
                return 0;
            }

            $progressBar = $this->output->createProgressBar($cryptos->count());
            $progressBar->start();

            foreach ($cryptos as $crypto) {
                try {
                    $ohlcv = $this->exchange->fetch_ohlcv($crypto->symbol, '15m', null, 120);
                    if (count($ohlcv) < 120) {
                        Log::warning("Insufficient candle data for {$crypto->symbol}");
                        $progressBar->advance();
                        continue;
                    }

                    foreach ($ohlcv as $candle) {
                        $timestamp = date('Y-m-d H:i:s', $candle[0] / 1000);
                        $open      = $candle[1];
                        $high      = $candle[2];
                        $low       = $candle[3];
                        $close     = $candle[4];
                        $volume    = $candle[5];

                        // Calculate indicators using the latest 50 candles
                        $highs[]   = $high;
                        $lows[]    = $low;
                        $closes[]  = $close;
                        $volumes[] = $volume;

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
                            ]
                        );
                    }

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
}
