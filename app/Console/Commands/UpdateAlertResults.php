<?php

namespace App\Console\Commands;

use App\Models\Alert;
use CCXT\binance;
use Illuminate\Console\Command;

class UpdateAlertResults extends Command
{
    protected $signature    = 'alerts:update-results';
    protected $description  = 'Update the results and statuses of active alerts based on price movements.';
    private static $tickers = null; // Cached tickers for efficiency

    public function handle()
    {
        $openAlerts = Alert::where('status', 'open')->get();

        if ($openAlerts->isEmpty()) {
            $this->info('No open alerts to update.');
            return;
        }

        $this->info('Updating results and statuses for active alerts...');
        $bar = $this->output->createProgressBar($openAlerts->count());
        $bar->start();

        // Fetch tickers once
        $this->fetchTickers();

        foreach ($openAlerts as $alert) {
            $currentPrice = $this->getCurrentPrice($alert->crypto->symbol);

            if ($currentPrice === null) {
                $this->warn("Price not found for {$alert->crypto->symbol}. Skipping...");
                continue;
            }

            // Update highest and lowest prices dynamically
            $alert->highest_price = max($alert->highest_price ?? $currentPrice, $currentPrice);
            $alert->lowest_price  = min($alert->lowest_price ?? $currentPrice, $currentPrice);

            // Determine result
            if ($alert->highest_price >= $alert->tp3) {
                $alert->update(['result' => 3, 'status' => 'closed']);
            } elseif ($alert->highest_price >= $alert->tp2) {
                $alert->update(['result' => 2, 'status' => 'partial']);
            } elseif ($alert->highest_price >= $alert->tp1) {
                $alert->update(['result' => 1, 'status' => 'partial']);
            } elseif ($alert->lowest_price <= $alert->stop_loss) {
                $alert->update(['result' => -1, 'status' => 'closed']);
            } else {
                $alert->save(); // No TP or SL hit; just update prices
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nResults and statuses updated successfully.");
    }

    /**
     * Fetch tickers once and cache them.
     */
    private function fetchTickers(): void
    {
        if (self::$tickers === null) {
            try {
                $binance       = new binance();
                self::$tickers = $binance->fetch_tickers();
            } catch (\Exception $e) {
                $this->error("Failed to fetch tickers: " . $e->getMessage());
                self::$tickers = [];
            }
        }
    }

    /**
     * Get the current price of a crypto using cached tickers.
     *
     * @param string $symbol
     * @return float|null
     */
    protected function getCurrentPrice(string $symbol): ?float
    {
        return self::$tickers[$symbol]['last'] ?? null;
    }
}
