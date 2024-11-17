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
    protected $description = 'Fetch all currently listed trading pairs from Binance and update the database.';

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
        $this->info('Fetching currently listed trading pairs from Binance...');

        try {
            // Fetch all market pairs from Binance
            $markets = $this->exchange->fetch_markets();

            // Filter for active trading pairs
            $activeMarkets = collect($markets)
                ->filter(fn($market) => $market['active'])
                ->pluck('symbol')
                ->toArray();

            if (empty($activeMarkets)) {
                $this->error('No active trading pairs found.');
                return 1;
            }

            // Sync with the database
            $this->syncCryptos($activeMarkets);

            $this->info('Successfully updated cryptos in the database.');
        } catch (\Exception $e) {
            $this->error('Error fetching trading pairs: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Sync cryptos in the database with active Binance markets.
     *
     * @param array $activeMarkets
     */
    protected function syncCryptos(array $activeMarkets)
    {
        $this->info('Updating cryptos in the database...');
        $progressBar = $this->output->createProgressBar(count($activeMarkets));
        $progressBar->start();

        // Mark all current cryptos as inactive
        Crypto::whereNotIn('symbol', $activeMarkets)->delete();

        // Insert or update active cryptos
        foreach ($activeMarkets as $symbol) {
            [$base, $quote] = explode('/', $symbol);

            Crypto::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'base_asset'   => $base,
                    'quote_asset'  => $quote,
                    'last_fetched' => now(),
                ]
            );

            // Advance the progress bar
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Crypto update complete.');
    }
}
