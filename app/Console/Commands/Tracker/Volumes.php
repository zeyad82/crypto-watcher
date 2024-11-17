<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class VolumesAlert extends Command
{
    protected $signature   = 'tracker:volumes-alert';
    protected $description = 'Track volume spikes and send alerts to Telegram.';
    protected $telegramBotToken;
    protected $telegramChatId;
    protected $httpClient;

    public function __construct()
    {
        parent::__construct();

        $this->telegramBotToken = config('volume.telegram.token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram.chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking new volume spikes...');

        // Fetch recent volume data
        $recentData = VolumeData::with('crypto')
            ->where('timestamp', '>=', now()->subMinutes(5))
            ->get();

        $newSpikes = [];
        foreach ($recentData as $data) {
            // Identify volume spikes
            $isSpike = $data->last_volume > ($data->ema_7 * 1.5);

            if (!$isSpike) {
                continue;
            }

            $crypto = $data->crypto;

            // Check if a recent alert was already sent
            if ($crypto->last_volume_alert && $crypto->last_volume_alert->greaterThanOrEqualTo(now()->subMinutes(10))) {
                continue; // Skip if an alert was sent within the last 10 minutes
            }

            $newSpikes[] = [
                'crypto'    => $crypto,
                'volume'    => $data->last_volume,
                'ema_7'     => $data->ema_7,
                'price'     => $data->close,
                'timestamp' => $data->timestamp,
            ];

            // Update the last_volume_alert timestamp
            $crypto->update(['last_volume_alert' => now()]);
        }

        if (empty($newSpikes)) {
            $this->info('No new volume spikes detected.');
            return 0;
        }

        // Create alert message
        $message = "🚨 *New Volume Spike Alerts* 🚨\n";
        foreach ($newSpikes as $spike) {
            $message .= sprintf(
                "\n*%s*\nVolume: %s\nEMA7: %s\nPrice: %s USDT\nTimestamp: %s\n",
                $spike['crypto']->symbol,
                number_format($spike['volume'], 2),
                number_format($spike['ema_7'], 2),
                number_format($spike['price'], 2),
                $spike['timestamp']->toDateTimeString()
            );
        }

        // Send the alert to Telegram
        $this->sendToTelegram($message);

        $this->info('New volume spike alerts sent to Telegram.');
        return 0;
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
