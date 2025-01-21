<?php

namespace App\Console\Commands\Tracker;

use App\Models\Alert;
use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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

        $cryptos = Crypto::orderByDesc('volume24')
            ->with('latest1m', 'latest15m', 'latest1h', 'latest4h', 'alerts') // Eager load the required data
            ->get();

        if ($cryptos->isEmpty()) {
            $this->info('No cryptos available for analysis.');
            return 0;
        }

        $newCrossovers = [];

        /**
         * @var Crypto $crypto
         */
        foreach ($cryptos as $crypto) {

            try {
                $currentTrend = $this->determineTrend($crypto);

                $previousTrend = $crypto->last_trend;

                // Check if the trend has changed
                if ($currentTrend != 'neutral' && !Cache::has("alerted_crypto_{$crypto->id}")) {
                    $crossover = [
                        'crypto'         => $crypto,
                        'trend'          => $currentTrend,
                        'previous_trend' => $previousTrend,
                        'ema_15'         => $crypto->latest15m->price_ema_15,
                        'ema_25'         => $crypto->latest15m->price_ema_25,
                        'ema_50'         => $crypto->latest15m->price_ema_50,
                        'price'          => $crypto->latest15m->close,
                        'timestamp'      => $crypto->latest15m->timestamp,
                        'atr'            => $crypto->latest15m->getNormalizedAtr(),
                        'macd_line'      => $crypto->latest15m->meta->get('macd_line'),
                        'signal_line'    => $crypto->latest15m->meta->get('signal_line'),
                        'histogram'      => $crypto->latest15m->meta->get('histogram'),
                        'rsi'            => $crypto->latest15m->meta->get('rsi'),
                        'adx'            => $crypto->latest15m->meta->get('adx'),
                        '+di'            => $crypto->latest15m->meta->get('+di'),
                        '-di'            => $crypto->latest15m->meta->get('-di'),
                        'entry'          => $crypto->latest1m->latest_price,
                    ] + $this->setup($crypto->latest15m, $currentTrend);

                    $newCrossovers[] = $crossover;

                    Alert::create([
                        'crypto_id' => $crypto->id,
                        'data' => $crypto->latest15m->toArray(),
                    ] + $crossover);

                    // Update the last_trend in the database
                    $crypto->update(['last_trend' => $currentTrend]);

                    // Send the alert for this crossover individually
                    $this->sendToTelegram($this->formatAlertMessage($crossover));

                    Cache::put("alerted_crypto_{$crypto->id}", true, now()->addHour());
                }
            } catch (\Throwable $th) {
                Log::error($th);
            }
         
        }

        if (empty($newCrossovers)) {
            $this->info('No new crossovers detected.');
            return 0;
        }

        $this->info('New crossover alerts sent to Telegram.');
        return 0;
    }

    /**
     * Determine the current trend based on Volume-Weighted MACD, RSI, and ATR values.
     *
     * @param VolumeData $data
     * @return string 'bullish', 'bearish', or 'neutral'
     */
    protected function determineTrend(Crypto $crypto): string
    {

        if(! $crypto->latest1m->meta) {
            return 'neutral';
        }
        
        $data = $crypto->latest15m;

        $normalizedAtr     = $data->getNormalizedAtr(); // Calculate ATR as a percentage of price
        $macdLine          = $data->meta->get('macd_line');
        $signalLine        = $data->meta->get('signal_line');
        $histogram         = $data->meta->get('histogram');
        $previousHistogram = $data->meta->get('previous_histogram');

        $macdLine1H        = $crypto->latest1h->meta->get('macd_line');
        $signalLine1H      = $crypto->latest1h->meta->get('signal_line');

        $adx     = $data->meta->get('adx');
        $plusDI  = $data->meta->get('+di');
        $minusDI = $data->meta->get('-di');

        // Retrieve RSI
        $rsi1m = $crypto->latest1m->meta->get('rsi');
        $rsi15m = $data->meta->get('rsi');

        $rsiBullish = $rsi1m < 40 && $rsi15m < 60;
        $rsiBearish = $rsi1m > 60 && $rsi15m > 45;

        // Histogram Momentum: Looking for growing positive or negative momentum
        $momentumUp   = $histogram > 0 && $histogram > $previousHistogram;
        $momentumDown = $histogram < 0 && $histogram < $previousHistogram;

        $bullish = $macdLine > $signalLine && $momentumUp && $rsiBullish && $macdLine1H > $signalLine1H ;
        $bearish = $macdLine < $signalLine && $momentumDown && $rsiBearish && $macdLine1H < $signalLine1H;

        if (env('LOG_ALERTS')) {
            Log::channel('observe')->info('trend check', [
                'crypto'            => $crypto->base_asset,
                'adx'               => $adx,
                'plusDI'            => $plusDI,
                'minusDI'           => $minusDI,
                'macdLine'          => $macdLine,
                'signalLine'        => $signalLine,
                'histogram'         => $histogram,
                'previousHistogram' => $previousHistogram,
                'bullish'           => $bullish,
                'bullishMCAD'       => $macdLine > $signalLine,
                'momentumUp'        => $momentumUp,
                'bullishDI'         => $plusDI > $minusDI,
                'bearish'           => $bearish,
                'bearishMCAD'       => $macdLine < $signalLine,
                'momentumDown'      => $momentumDown,
                'bearishDI'         => $minusDI > $plusDI,
            ]);
        }

        // Include ADX confirmation for trend strength
        if ($adx > 23 && $normalizedAtr > 2) {
            if ($bullish) {
                return 'bullish';
            }
            if ($bearish) {
                return 'bearish';
            }
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
     * Format the alert message.
     *
     * @param array $crossover
     * @return string
     */
    protected function formatAlertMessage(array $crossover): string
    {
        $trend = strtoupper($crossover['trend']);

        return sprintf(
            "*#%s*\nTrend: %s\n\n"
            . "MACD Line: %s\nMACD Signal: %s\nMACD Histogram: %s\n\n"
            . "EMA15: %s\nEMA25: %s\nEMA50: %s\n\n"
            . "ATR: %s%%\nRSI: %s\nADX: %s\n+DI: %s\n-DI: %s\n\n"
            . "Price: %s USDT\nTP1: %s\nTP2: %s\nTP3: %s\nSL: %s\n\n"
            . "Time: %s\n\n"
            . "Performance Report:\n%s\n\n",
            $crossover['crypto']->symbol,
            $trend,
            round($crossover['macd_line'], 5),
            round($crossover['signal_line'], 5),
            round($crossover['histogram'], 5),
            round($crossover['ema_15'], 8),
            round($crossover['ema_25'], 8),
            round($crossover['ema_50'], 8),
            round($crossover['atr'], 2),
            round($crossover['rsi'], 2),
            round($crossover['adx'], 2),
            round($crossover['+di'], 2),
            round($crossover['-di'], 2),
            round($crossover['price'], 8),
            round($crossover['tp1'], 8),
            round($crossover['tp2'], 8),
            round($crossover['tp3'], 8),
            round($crossover['stop_loss'], 8),
            Carbon::parse($crossover['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s'),
            $this->generatePerformanceReport($crossover['crypto'])
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
        $chunk  = "";
        $chunks = [];

        foreach ($alerts as $alert) {
            // Add the next alert if it fits within the 4000-character limit
            if (strlen($chunk) + strlen($alert) + 2 <= 4000) {
                $chunk .= ($chunk === "" ? "" : "\n\n") . $alert;
            } else {
                // Store the current chunk and start a new one
                $chunks[] = $chunk;
                $chunk    = $alert;
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
                        'chat_id'    => $this->telegramChatId,
                        'text'       => $chunkMessage,
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
        $rewardRiskRatio = 2; // Example: 2:1 ratio
        $atr = $data->meta->get('atr');
        $entryPrice = $data->latest_price;

        $takeProfit = [];

        if ($trend == 'bullish') {
            $stopLoss = $entryPrice - (1.5 * $atr);
            $tp = $entryPrice + ($rewardRiskRatio * (1.5 * $atr));
            $takeProfit['tp1'] = $tp;
            $takeProfit['tp2'] = $tp + $atr;
            $takeProfit['tp3'] = $tp + (2 * $atr);
        } else {
            $stopLoss = $entryPrice + (1.5 * $atr);
            $tp = $entryPrice - ($rewardRiskRatio * (1.5 * $atr));

            $takeProfit['tp1'] = $tp;
            $takeProfit['tp2'] = $tp - $atr;
            $takeProfit['tp3'] = $tp - (2 * $atr);
        }

        return [
            'stop_loss' => $stopLoss,
        ] + $takeProfit;
    }
}
