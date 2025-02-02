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
        $cryptos = Crypto::with(['alerts' => function ($alerts) {
            $alerts->where('status', 'closed')
                ->orderBy('created_at', 'desc'); 
        }])->get();

        $data           = [];
        $totalAlertsAll = 0;
        $totalTP1Hits   = 0;
        $totalTP2Hits   = 0;
        $totalTP3Hits   = 0;
        $totalSLHits    = 0;

        foreach ($cryptos as $crypto) {
            $totalAlerts = $crypto->alerts->count();

            if ($totalAlerts > 0) {
                $tp1Hits = $crypto->alerts->where('result', 1)->count();
                $tp2Hits = $crypto->alerts->where('result', 2)->count();
                $tp3Hits = $crypto->alerts->where('result', 3)->count();
                $slHits  = $crypto->alerts->where('result', -1)->count();

                $winningRate = (($tp1Hits + $tp2Hits + $tp3Hits) / $totalAlerts) * 100;

                // Get the last alert time
                $lastAlertTime = $crypto->alerts->first()->created_at->format('Y-m-d H:i:s');

                $data[] = [
                    'Symbol'       => $crypto->symbol,
                    'Signals'      => $totalAlerts,
                    'TP1 (%)' => number_format(($tp1Hits / $totalAlerts) * 100, 2),
                    'TP2 (%)'      => number_format(($tp2Hits / $totalAlerts) * 100, 2),
                    'TP3 (%)'      => number_format(($tp3Hits / $totalAlerts) * 100, 2),
                    'SL (%)'       => number_format(($slHits / $totalAlerts) * 100, 2),
                    'Winning Rate' => number_format($winningRate, 2),
                    'Last Alert'   => $lastAlertTime, // Include last alert time in the data
                ];

                // Aggregate totals for the summary row
                $totalAlertsAll += $totalAlerts;
                $totalTP1Hits += $tp1Hits;
                $totalTP2Hits += $tp2Hits;
                $totalTP3Hits += $tp3Hits;
                $totalSLHits += $slHits;
            }
        }

        if (empty($data)) {
            $this->info('No alerts data available to analyze.');
            return;
        }

        // Sort data by Last Alert time (descending)
        usort($data, function ($a, $b) {
            return strtotime($a['Last Alert']) <=> strtotime($b['Last Alert']);
        });

        // Add the total result row
        $totalWinningRate = (($totalTP1Hits + $totalTP2Hits + $totalTP3Hits) / $totalAlertsAll) * 100;

        $data[] = [
            'Symbol'       => 'TOTAL',
            'Signals'      => $totalAlertsAll,
            'TP1 (%)' => number_format(($totalTP1Hits / $totalAlertsAll) * 100, 2),
            'TP2 (%)'      => number_format(($totalTP2Hits / $totalAlertsAll) * 100, 2),
            'TP3 (%)'      => number_format(($totalTP3Hits / $totalAlertsAll) * 100, 2),
            'SL (%)'       => number_format(($totalSLHits / $totalAlertsAll) * 100, 2),
            'Winning Rate' => number_format($totalWinningRate, 2),
            'Last Alert'   => '-', // Total row doesn't need a last alert
        ];

        // Display the data in a table format
        $this->table(
            ['Symbol', 'Signals', 'TP1 (%)', 'TP2 (%)', 'TP3 (%)', 'SL (%)', 'Winning Rate', 'Last Alert'],
            $data
        );
    }

}
