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

        // Fetch the top 120 cryptos by 24-hour trading volume
        $topCryptos = Crypto::orderByDesc('volume24')
            ->pluck('id');

        if ($topCryptos->isEmpty()) {
            $this->info('No cryptos available for analysis.');
            return 0;
        }

        // Fetch recent volume data
        $recentData = VolumeData::with('crypto.alerts', 'crypto.latest1h')
            ->whereIn('volume_data.crypto_id', $topCryptos)
            ->where('timeframe', '15m') // Ensure the outer query also checks the timeframe
            ->joinSub(
                VolumeData::select('crypto_id', VolumeData::raw('MAX(timestamp) AS latest_timestamp'))
                    ->where('timeframe', '15m') // Only consider 15m timeframe for the subquery
                    ->groupBy('crypto_id'),
                'latest',
                function ($join) {
                    $join->on('volume_data.crypto_id', '=', 'latest.crypto_id')
                        ->on('volume_data.timestamp', '=', 'latest.latest_timestamp');
                }
            )
            ->get();

        if(request('dd')) {
            dd($recentData->pluck('timestamp'));
        }


        $newCrossovers = [];

        /**
         * @var VolumeData $data
         */
        foreach ($recentData as $data) {
            $currentTrend = $this->determineTrend($data);

            // Get the previous trend from the crypto record
            $crypto        = $data->crypto;
            $previousTrend = $crypto->last_trend;

            // Check if the trend has changed
            if ($currentTrend !== $previousTrend && $currentTrend != 'neutral') {
                $crossover = [
                    'crypto'         => $crypto,
                    'trend'          => $currentTrend,
                    'previous_trend' => $previousTrend,
                    'ema_15'         => $data->price_ema_15,
                    'ema_25'         => $data->price_ema_25,
                    'ema_50'         => $data->price_ema_50,
                    'price'          => $data->close,
                    'timestamp'      => $data->timestamp,
                    'atr'            => $data->getNormalizedAtr(),
                    'macd_line'      => $data->meta->get('macd_line'),
                    'signal_line'    => $data->meta->get('signal_line'),
                    'histogram'      => $data->meta->get('histogram'),
                    'rsi'            => $data->meta->get('rsi'),
                    'adx'            => $data->meta->get('adx'),
                    '+di'            => $data->meta->get('+di'),
                    '-di'            => $data->meta->get('-di'),
                    'entry'          => $data->latest_price,
                ] + $this->setup($data, $currentTrend);

                $newCrossovers[] = $crossover;

                Alert::create([
                    'crypto_id' => $data->crypto_id,
                    'volume_id' => $data->id,
                ] + $crossover);

                // Update the last_trend in the database
                $crypto->update(['last_trend' => $currentTrend]);

                // Send the alert for this crossover individually
                $this->sendToTelegram($this->formatAlertMessage($crossover));
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
    protected function determineTrend(VolumeData $data): string
    {
        // $normalizedAtr = $data->getNormalizedAtr(); // Calculate ATR as a percentage of price

        // Retrieve VW-MACD data
        $macdLine          = $data->meta->get('macd_line');
        $signalLine        = $data->meta->get('signal_line');
        $histogram         = $data->meta->get('histogram');
        $previousHistogram = $data->meta->get('previous_histogram');

        $macdLine1H        = $data->crypto->latest1h->meta->get('macd_line');
        $signalLine1H      = $data->crypto->latest1h->meta->get('signal_line');

        $adx     = $data->meta->get('adx');
        $plusDI  = $data->meta->get('+di');
        $minusDI = $data->meta->get('-di');

        // Retrieve RSI
        $rsi = $data->meta->get('rsi');

        // Histogram Momentum: Looking for growing positive or negative momentum
        $momentumUp   = $histogram > 0 && $histogram > $previousHistogram;
        $momentumDown = $histogram < 0 && $histogram < $previousHistogram;

        $bullish = $macdLine > $signalLine && $momentumUp && $rsi < 45 && $macdLine1H > $signalLine1H ;
        $bearish = $macdLine < $signalLine && $momentumDown && $rsi > 65 && $macdLine1H < $signalLine1H;

        if (env('LOG_ALERTS')) {
            Log::channel('observe')->info('trend check', [
                'crypto'            => $data->crypto->base_asset,
                'adx'               => $adx,
                'plusDI'            => $plusDI,
                'minusDI'           => $minusDI,
                'rsi'               => $rsi,
                'macdLine'          => $macdLine,
                'signalLine'        => $signalLine,
                'histogram'         => $histogram,
                'previousHistogram' => $previousHistogram,
                'bullish'           => $bullish,
                'bullishMCAD'       => $macdLine > $signalLine,
                'momentumUp'        => $momentumUp,
                'bullishRSI'        => $rsi < 40,
                'bullishDI'         => $plusDI > $minusDI,
                'bearish'           => $bearish,
                'bearishMCAD'       => $macdLine < $signalLine,
                'momentumDown'      => $momentumDown,
                'bearishRSI'        => $rsi > 75,
                'bearishDI'         => $minusDI > $plusDI,
            ]);
        }

        // Include ADX confirmation for trend strength
        if ($adx > 25) {
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
        if ($trend == 'bullish') {
            return [
                'stop_loss' => $data->latest_price - (1.5 * $data->meta->get('atr')),
                'tp1'       => $data->latest_price + (1 * $data->meta->get('atr')),
                'tp2'       => $data->latest_price + (2 * $data->meta->get('atr')),
                'tp3'       => $data->latest_price + (3 * $data->meta->get('atr')),
            ];
        } else {
            return [
                'stop_loss' => $data->latest_price + (1.5 * $data->meta->get('atr')),
                'tp1'       => $data->latest_price - (1 * $data->meta->get('atr')),
                'tp2'       => $data->latest_price - (2 * $data->meta->get('atr')),
                'tp3'       => $data->latest_price - (3 * $data->meta->get('atr')),
            ];
        }
    }
}
