<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class EmasAlert extends Command
{
    protected $signature   = 'tracker:emas-alert';
    protected $description = 'Track new EMA crossovers and send alerts to Telegram.';
    protected $telegramBotToken;
    protected $telegramChatId;
    protected $httpClient;

    public function __construct()
    {
        parent::__construct();

        $this->telegramBotToken = config('volume.telegram_token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram_chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking new EMA crossovers...');

        // Fetch recent EMA data
        $recentData = VolumeData::with('crypto')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->get();

        $newCrossovers = [];
        foreach ($recentData as $data) {
            $currentTrend = $this->determineTrend($data);

            // Get the previous trend from the crypto record
            $crypto        = $data->crypto;
            $previousTrend = $crypto->last_trend;

            // Check if the trend has changed
            if ($currentTrend !== $previousTrend) {
                $newCrossovers[] = [
                    'crypto'         => $crypto,
                    'current_trend'  => $currentTrend,
                    'previous_trend' => $previousTrend,
                    'ema_7' => $data->price_ema_7,
                    'ema_15' => $data->price_ema_15,
                    'price' => $data->close,
                    'timestamp' => $data->timestamp,
                ];

                // Update the last_trend in the database
                $crypto->update(['last_trend' => $currentTrend]);
            }
        }

        if (empty($newCrossovers)) {
            $this->info('No new EMA crossovers detected.');
            return 0;
        }

        // Send the alerts to Telegram
        $message = "📈 *New EMA Crossover Alerts* 📉\n";
        foreach ($newCrossovers as $crossover) {
            $trend = strtoupper($crossover['current_trend']);
            $message .= sprintf(
                "\n*%s*\nNew Trend: %s (Previous: %s)\nEMA7: %s\nEMA15: %s\nEMA50: %s\nPrice: %s USDT\nTimestamp: %s\n",
                $crossover['crypto']->symbol,
                $trend,
                strtoupper($crossover['previous_trend'] ?? 'neutral'),
                number_format($crossover['ema_7'], 2),
                number_format($crossover['ema_15'], 2),
                number_format($crossover['ema_50'], 2),
                number_format($crossover['price'], 2),
                $crossover['timestamp']->toDateTimeString()
            );
        }

        $this->sendToTelegram($message);
        $this->info('New EMA crossover alerts sent to Telegram.');
        return 0;
    }

    /**
     * Determine the current trend based on EMA values.
     *
     * @param VolumeData $data
     * @return string 'bullish', 'bearish', or 'neutral'
     */
    protected function determineTrend(VolumeData $data): string
    {
        if ($data->price_ema_7 > $data->price_ema_15) {
            return 'bullish';
        }

        if ($data->price_ema_7 < $data->price_ema_15) {
            return 'bearish';
        }

        return 'neutral';
    }

    /**
     * Send a message to Telegram.
     *
     * @param string $message
     */
    protected function sendToTelegram(string $message)
    {
        $url = "https://api.telegram.org/bot{$this->telegramBotToken}/sendMessage";

        try {
            $this->httpClient->post($url, [
                'json' => [
                    'chat_id'    => $this->telegramChatId,
                    'text'       => $message,
                    'parse_mode' => 'Markdown',
                ],
            ]);
        } catch (\Exception $e) {
            $this->error("Failed to send Telegram alert: " . $e->getMessage());
        }
    }
}
