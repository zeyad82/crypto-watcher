<?php

namespace App\Console\Commands\Tracker;

use App\Models\Alert;
use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrossoversAlert extends Command
{
    protected $signature   = 'tracker:crossovers-alert';
    protected $description = 'Track new crossovers and send alerts to Telegram.';
    protected $telegramBotToken;
    protected $telegramChatId;
    protected $httpClient;

    public function __construct()
    {
        parent::__construct();

        $this->telegramBotToken = config('volume.telegram.token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram.emas_chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking new EMA crossovers...');

        // Fetch recent EMA data
        $recentData = VolumeData::with('crypto.alerts')
        ->selectRaw('*, MAX(timestamp) OVER (PARTITION BY crypto_id) AS latest_timestamp')
        ->whereRaw('timestamp = (SELECT MAX(timestamp) FROM volume_data v WHERE v.crypto_id = volume_data.crypto_id)')
        ->get();

        $newCrossovers = [];

        /**
        * @var VolumeData $data
        */
        foreach ($recentData as $data) {
            $currentTrend = $this->determineTrend($data);

            // Get the previous trend from the crypto record
            $crypto        = $data->crypto;
            $previousTrend = $crypto->last_trend;

            // Update the last_trend in the database
            $crypto->update(['last_trend' => $currentTrend]);

            // Check if the trend has changed
            if ($currentTrend !== $previousTrend && $currentTrend != 'neutral') {
                $crossover = [
                    'crypto'         => $crypto,
                    'trend'  => $currentTrend,
                    'previous_trend' => $previousTrend,
                    'ema_15' => $data->price_ema_15,
                    'ema_25' => $data->price_ema_25,
                    'ema_50' => $data->price_ema_50,
                    'price' => $data->close,
                    'timestamp' => $data->timestamp,
                    'atr'       => $data->getNormalizedAtr(),
                    'macd_line' => $data->meta->get('vw_macd_line'),
                    'signal_line' => $data->meta->get('vw_signal_line'),
                    'histogram' => $data->meta->get('vw_histogram'),
                    'rsi' => $data->meta->get('rsi'),
                    'entry'          => $data->latest_price,
                ] + $this->setup($data, $currentTrend);

                $newCrossovers[] = $crossover;

                Alert::create([
                    'crypto_id' => $data->crypto_id,
                    'volume_id' => $data->id,
                ] + $crossover);
            }
        }

        if (empty($newCrossovers)) {
            $this->info('No new crossovers detected.');
            return 0;
        }

        // Send the alerts to Telegram
        $message = "*New Alert* 📈📉\n\n";
        foreach ($newCrossovers as $crossover) {
            $trend = strtoupper($crossover['trend']);
            $message .= sprintf(
                "*#%s*\nNew Trend: %s (Previous: %s)\n\n"
                . "MACD Line: %s\nMACD Signal: %s\nMACD Histogram: %s\n\n"
                . "EMA15: %s\nEMA25: %s\nEMA50: %s\n\n"
                . "ATR: %s%%\nRSI: %s\n\n"
                . "Price: %s USDT\nTP1: %s\nTP2: %s\nTP3: %s\nSL: %s\n\n"
                . "Time: %s\n\n"
                . "Performance Report:\n%s\n\n",
                $crossover['crypto']->symbol,
                $trend,
                strtoupper($crossover['previous_trend'] ?? 'neutral'),
                round($crossover['macd_line'], 5),
                round($crossover['signal_line'], 5),
                round($crossover['histogram'], 5),
                round($crossover['ema_15'], 8),
                round($crossover['ema_25'], 8),
                round($crossover['ema_50'], 8),
                round($crossover['atr'], 2),
                round($crossover['rsi'], 2),
                round($crossover['price'], 8),
                round($crossover['tp1'], 8),
                round($crossover['tp2'], 8),
                round($crossover['tp3'], 8),
                round($crossover['stop_loss'], 8),
                Carbon::parse($crossover['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s'),
                $this->generatePerformanceReport($crossover['crypto'])
            );
        }

        $this->sendToTelegram($message);
        $this->info('New crossover alerts sent to Telegram.');
        return 0;
    }

    /**
    * Determine the current trend based on Volume-Weighted MACD, RSI, and ATR values.
    *
    * @param VolumeData $data
    * @return string 'bullish', 'bearish', or 'neutral'
    */
    protected function determineTrend(VolumeData $data): string
    {
        $normalizedAtr = $data->getNormalizedAtr(); // Calculate ATR as a percentage of price

        if ($normalizedAtr < 2) {
            return 'neutral';
        }

        // Retrieve VW-MACD data
        $vwMacdLine   = $data->meta->get('vw_macd_line');
        $vwSignalLine = $data->meta->get('vw_signal_line');
        $vwHistogram  = $data->meta->get('vw_histogram');
        $previousHistogram = $data->meta->get('previous_histogram');

        // Retrieve RSI
        $rsi = $data->meta->get('rsi');

        // Histogram Momentum: Looking for growing positive or negative momentum
        $momentumUp   = $vwHistogram > 0 && $vwHistogram > $previousHistogram;
        $momentumDown = $vwHistogram < 0 && $vwHistogram < $previousHistogram;

        // Bullish Trend: VW-MACD Line > Signal Line + RSI < 30 (oversold)
        if ($vwMacdLine > $vwSignalLine && $momentumUp && $rsi < 55) {
            return 'bullish';
        }

        // Bearish Trend: VW-MACD Line < Signal Line + RSI > 70 (overbought)
        if ($vwMacdLine < $vwSignalLine && $momentumDown && $rsi > 45) {
            return 'bearish';
        }

        return 'neutral';
    }

    protected function generatePerformanceReport($crypto)
    {
        $alerts = $crypto->alerts;

        if ($alerts->isEmpty()) {
            return "No past performance data available.";
        }

        $wins   = $alerts->whereIn('result', [1, 2, 3])->count();
        $losses = $alerts->where('result', -1)->count();
        $total  = $alerts->count();

        $tp1Hits = $alerts->where('result', 1)->count();
        $tp2Hits = $alerts->where('result', 2)->count();
        $tp3Hits = $alerts->where('result', 3)->count();

        $winRate = $total > 0 ? round(($wins / $total) * 100, 2) : 0;

        return sprintf(
            "Total Signals: %d\nWins: %d\nLosses: %d\nWin Rate: %s%%\n\nTP1 Hits: %d\nTP2 Hits: %d\nTP3 Hits: %d",
            $total,
            $wins,
            $losses,
            $winRate,
            $tp1Hits,
            $tp2Hits,
            $tp3Hits
        );
    }

    /**
     * Send a message to Telegram.
     *
     * @param string $message
     */
    protected function sendToTelegram(string $message)
    {
        $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";

        // Split the message by individual alerts (based on new lines for each alert)
        $alerts = explode("\n\n", trim($message)); // Assuming each alert is separated by two newlines
        $chunk = "";
        $chunks = [];

        foreach ($alerts as $alert) {
            // Add the next alert if it fits within the 4000-character limit
            if (strlen($chunk) + strlen($alert) + 2 <= 4000) {
                $chunk .= ($chunk === "" ? "" : "\n\n") . $alert;
            } else {
                // Store the current chunk and start a new one
                $chunks[] = $chunk;
                $chunk = $alert;
            }
        }

        // Add the last chunk
        if ($chunk !== "") {
            $chunks[] = $chunk;
        }

        try {
            foreach ($chunks as $chunkMessage) {
                $this->httpClient->post($url, [
                    'json' => [
                        'chat_id' => $this->telegramChatId,
                        'text' => $chunkMessage,
                        'parse_mode' => 'Markdown',
                    ],
                ]);
            }
        } catch (\Exception $e) {
            $this->error("Failed to send Telegram alert: " . $e->getMessage());
        }
    }

    protected function setup($data, $trend)
    {
        if($trend == 'bullish') {
            return [
                'stop_loss'      => $data->latest_price - (1.5 * $data->meta->get('atr')),
                'tp1'            => $data->latest_price + (1 * $data->meta->get('atr')),
                'tp2'            => $data->latest_price + (2 * $data->meta->get('atr')),
                'tp3'            => $data->latest_price + (3 * $data->meta->get('atr')),
            ];
        } else {
            return [
                'stop_loss'      => $data->latest_price + (1.5 * $data->meta->get('atr')),
                'tp1'            => $data->latest_price - (1 * $data->meta->get('atr')),
                'tp2'            => $data->latest_price - (2 * $data->meta->get('atr')),
                'tp3'            => $data->latest_price - (3 * $data->meta->get('atr')),
            ];
        }
    }
}
