<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use ccxt\binance;
use Illuminate\Console\Command;

class FetchCryptos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:fetch-cryptos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all currently listed USDT trading pairs from Binance, including 24-hour volume, and update the database.';

    /**
     * Binance API instance.
     *
     * @var binance
     */
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching currently listed USDT trading pairs and their 24-hour volumes from Binance...');

        try {
            // Fetch all market pairs from Binance
            $markets = $this->exchange->fetch_markets();

            // Fetch 24-hour tickers for volumes
            $tickers = $this->exchange->fetch_tickers();

            // Filter for active USDT trading pairs
            $usdtMarkets = collect($markets)
                ->filter(fn($market) => $market['active'] && $market['quote'] === 'USDT')
                ->map(function ($market) use ($tickers) {
                    $symbol = $market['symbol'];
                    $volume = $tickers[$symbol]['quoteVolume'] ?? 0; // Get 24-hour volume
                    $market['volume'] = $volume;
                    return $market;
                })
                ->toArray();

            if (empty($usdtMarkets)) {
                $this->error('No active USDT trading pairs found.');
                return 1;
            }

            // Sync with the database
            $this->syncCryptos($usdtMarkets);

            $this->info('Successfully updated USDT trading pairs in the database.');
        } catch (\Exception $e) {
            $this->error('Error fetching trading pairs: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Sync cryptos in the database with active Binance USDT markets.
     *
     * @param array $usdtMarkets
     */
    protected function syncCryptos(array $usdtMarkets)
    {
        $this->info('Updating USDT trading pairs in the database...');
        $progressBar = $this->output->createProgressBar(count($usdtMarkets));
        $progressBar->start();

        // Collect existing USDT symbols in the database
        $existingSymbols = Crypto::pluck('symbol')->toArray();

        // Update or create USDT cryptos
        foreach ($usdtMarkets as $market) {
            $symbol = $market['symbol'];
            $base   = $market['base'];
            $quote  = $market['quote'];
            $volume = $market['volume']; // Include 24-hour volume

            Crypto::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'base_asset'   => $base,
                    'quote_asset'  => $quote,
                    'volume24'     => $volume, // Save volume
                    'last_fetched' => now(),
                ]
            );

            // Advance the progress bar
            $progressBar->advance();
        }

        // Remove cryptos no longer active in USDT markets
        $activeSymbols = array_column($usdtMarkets, 'symbol');
        Crypto::whereNotIn('symbol', $activeSymbols)->delete();

        $progressBar->finish();
        $this->newLine();
        $this->info('USDT trading pairs update complete.');
    }
}
