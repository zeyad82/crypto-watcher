<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        $this->telegramBotToken = config('volume.telegram.token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram.emas_chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking new EMA crossovers...');

        // Fetch recent EMA data
        $recentData = VolumeData::with('crypto')
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
                $newCrossovers[] = [
                    'crypto'         => $crypto,
                    'current_trend'  => $currentTrend,
                    'previous_trend' => $previousTrend,
                    'ema_15' => $data->price_ema_15,
                    'ema_25' => $data->price_ema_25,
                    'ema_50' => $data->price_ema_50,
                    'price' => $data->close,
                    'timestamp' => $data->timestamp,
                    'atr'       => $data->meta->get('atr'),
                ];

                Log::info('crossover', $newCrossovers + ['strong' => $data->meta->get('atr') > 0.5, 'atr ' => $data->meta->get('atr')]);
            }
        }

        if (empty($newCrossovers)) {
            $this->info('No new EMA crossovers detected.');
            return 0;
        }

        // Send the alerts to Telegram
        $message = "*New Alerts* 📈📉\n";
        foreach ($newCrossovers as $crossover) {
            $trend = strtoupper($crossover['current_trend']);
            $message .= sprintf(
                "\n*%s*\nNew Trend: %s (Previous: %s)\nEMA15: %s\nEMA25: %s\nEMA50: %s\nATR: %s\nPrice: %s USDT\nTime: %s\n",
                $crossover['crypto']->symbol,
                $trend,
                strtoupper($crossover['previous_trend'] ?? 'neutral'),
                number_format($crossover['ema_15'], 8),
                number_format($crossover['ema_25'], 8),
                number_format($crossover['ema_50'], 8),
                $crossover['atr'],
                number_format($crossover['price'], 8),
                $crossover['timestamp']
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
        if($data->meta->get('atr') < 0.5) {
            return 'neutral';
        }

        if ($data->price_ema_15 > $data->price_ema_25 && $data->price_ema_25 > $data->price_ema_50) {
            return 'bullish';
        }

        if ($data->price_ema_15 < $data->price_ema_25 && $data->price_ema_25 < $data->price_ema_50) {
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

}
