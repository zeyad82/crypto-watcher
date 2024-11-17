<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use Carbon\Carbon;
use ccxt\binance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchCryptos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:fetch-pairs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all USDT trading pairs from Binance and store them in the database.';

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
        $this->info('Fetching USDT trading pairs from Binance...');

        try {
            // Fetch all market pairs from Binance
            $markets = $this->exchange->fetch_markets();

            // Filter USDT pairs
            $usdtMarkets = array_filter($markets, function ($market) {
                return str_ends_with($market['symbol'], 'USDT');
            });

            // Initialize progress bar
            $progressBar = $this->output->createProgressBar(count($usdtMarkets));
            $progressBar->start();

            foreach ($usdtMarkets as $market) {
                // Insert or update the crypto record
                Crypto::updateOrCreate(
                    ['symbol' => $market['symbol']],
                    [
                        'base_asset'   => $market['base'],
                        'quote_asset'  => $market['quote'],
                        'last_fetched' => Carbon::now(),
                    ]
                );

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->info("\nSuccessfully stored USDT trading pairs in the database.");
        } catch (\Exception $e) {
            Log::error('Error fetching trading pairs: ' . $e->getMessage());
            $this->error('Failed to fetch trading pairs. Check the logs for details.');
        }

        return 0;
    }
}
