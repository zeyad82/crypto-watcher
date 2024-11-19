<?php

namespace App\Console\Commands;

use App\Models\Crypto;
use Illuminate\Console\Command;

class AlertPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:performance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the performance of alerts for each crypto symbol and display as a table.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Fetch all crypto symbols
        $cryptos = Crypto::with('alerts')->get();

        $data = [];

        foreach ($cryptos as $crypto) {
            $totalAlerts = $crypto->alerts->count();

            if ($totalAlerts > 0) {
                $tp1Hits = $crypto->alerts->where('result', 1)->count();
                $tp2Hits = $crypto->alerts->where('result', 2)->count();
                $tp3Hits = $crypto->alerts->where('result', 3)->count();
                $slHits  = $crypto->alerts->where('result', -1)->count();

                $winningRate = (($tp1Hits + $tp2Hits + $tp3Hits) / $totalAlerts) * 100;

                $data[] = [
                    'Symbol'       => $crypto->symbol,
                    'Signals'      => $crypto->alerts->count(),
                    'TP1 (%)'      => number_format(($tp1Hits / $totalAlerts) * 100, 2),
                    'TP2 (%)'      => number_format(($tp2Hits / $totalAlerts) * 100, 2),
                    'TP3 (%)'      => number_format(($tp3Hits / $totalAlerts) * 100, 2),
                    'SL (%)'       => number_format(($slHits / $totalAlerts) * 100, 2),
                    'Winning Rate' => number_format($winningRate, 2),
                ];
            }
        }

        if (empty($data)) {
            $this->info('No alerts data available to analyze.');
            return;
        }

        // Display the data in a table format
        $this->table(
            ['Symbol', 'TP1 (%)', 'TP2 (%)', 'TP3 (%)', 'SL (%)', 'Winning Rate'],
            $data
        );
    }
}
