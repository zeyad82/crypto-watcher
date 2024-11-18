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
        ->selectRaw('*, MAX(timestamp) OVER (PARTITION BY crypto_id) AS latest_timestamp')
        ->whereRaw('timestamp = (SELECT MAX(timestamp) FROM volume_data v WHERE v.crypto_id = volume_data.crypto_id)')
        ->get();


        $newSpikes = [];

        /**
         * @var VolumeData $data
         */
        foreach ($recentData as $data) {
            $crypto = $data->crypto;

            // Check if a recent alert was already sent
            if ($crypto->last_volume_alert && $crypto->last_volume_alert->greaterThanOrEqualTo(now()->subMinutes(20))) {
                continue; // Skip if an alert was sent within the last 10 minutes
            }

            // Identify volume spikes
            $isSpike = $data->last_volume > ($data->vma_15 * 2);
            $amplitudePercent = $this->calculateAmplitudePercent($data->high, $data->low); // Calculate percentage change

            if (! $isSpike || $amplitudePercent < 1.2) {
                continue;
            }

            $newSpikes[] = [
                'crypto'    => $crypto,
                'volume'    => $data->last_volume * $data->close,
                'vma_15'    => $data->vma_15 * $data->close,
                'price'     => $data->close,
                'amplitude' => $amplitudePercent
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
                "\n*%s*\nVolume: %s USDT\nAmplitude: %s%%\nEMA15: %s USDT\nPrice: %s USDT\n",
                $spike['crypto']->symbol,
                number_format($spike['volume'], 2),
                number_format($spike['amplitude'], 2),
                number_format($spike['vma_15'], 2),
                number_format($spike['price'], 8)
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

    /**
     * Calculate the percentage change between high and low prices.
     *
     * @param float $high
     * @param float $low
     * @return float
     */
    protected function calculateAmplitudePercent(float $high, float $low): float
    {
        if ($low == 0) {
            return 0; // Avoid division by zero
        }
        return (($high - $low) / $low) * 100;
    }
}
