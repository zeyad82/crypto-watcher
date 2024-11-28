<?php

namespace App\Console\Commands;

use App\Models\Alert;
use CCXT\binance;
use Illuminate\Console\Command;

class UpdateAlertResults extends Command
{
    protected $signature    = 'alerts:update-results';
    protected $description  = 'Update the results and statuses of active alerts based on price movements.';
    private static $tickers = null;

    public function handle()
    {
        $openAlerts = Alert::whereIn('status', ['open', 'partial'])->get();

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

            if($alert->trend === 'bullish') {
                if ($alert->highest_price >= $alert->tp3) {
                    $alert->result = 3; 
                    $alert->status = 'closed';
                } elseif ($alert->highest_price >= $alert->tp2) {
                    $alert->result = 2; 
                    $alert->status = 'partial';
                } elseif ($alert->highest_price >= $alert->tp1) {
                    $alert->result = 1; 
                    $alert->status = 'partial';
                } elseif ($alert->lowest_price <= $alert->stop_loss) {
                    if($alert->result === 1) {
                        $alert->result = -11;
                    } elseif($alert->result === 2) {
                        $alert->result = -2;
                    } else {
                        $alert->result = -1;
                    }

                    $alert->status = 'closed';
                }
            } 

            if ($alert->trend === 'bearish') {
                if ($alert->lowest_price <= $alert->tp3) {
                    $alert->result = 3; 
                    $alert->status = 'closed';
                } elseif ($alert->lowest_price <= $alert->tp2) {
                    $alert->result = 2; 
                    $alert->status = 'partial';
                } elseif ($alert->lowest_price <= $alert->tp1) {
                    $alert->result = 1; 
                    $alert->status = 'partial';
                } elseif ($alert->highest_price >= $alert->stop_loss) {
                    if($alert->result === 1) {
                        $alert->result = -11;
                    } elseif($alert->result === 2) {
                        $alert->result = -2;
                    } else {
                        $alert->result = -1;
                    }

                    $alert->status = 'closed';
                }
            }

            $alert->save();

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
