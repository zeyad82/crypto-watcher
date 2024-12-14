<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class VolumesAlert extends Command
{
    protected $signature   = 'tracker:volumes-alert';
    protected $description = 'Track volume spikes and send alerts to Telegram.';

    protected $telegramBotToken;
    protected $telegramChatId;
    protected $httpClient;

    protected $rank;

    public function __construct()
    {
        parent::__construct();

        $this->telegramBotToken = config('volume.telegram.token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram.volumes_chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking new volume spikes...');

        // Fetch all cryptos ordered by 24-hour volume
        $allCryptos = Crypto::with('latest1m', 'latest15m', 'latest1h')->orderByDesc('volume24')->get();

        $this->rank = [
            'top20'  => $allCryptos->take(20)->pluck('id')->toArray(),
            'top50'  => $allCryptos->slice(20, 30)->pluck('id')->toArray(),
            'top120' => $allCryptos->slice(50, 70)->pluck('id')->toArray(),
        ];

        $newSpikes = [];

        /**
         * @var Crypto $crypto
         */
        foreach ($allCryptos as $crypto) {
            $data = $crypto->latest15m;

            // Check if a recent alert was already sent
            if ($crypto->last_volume_alert && $crypto->last_volume_alert->equalTo($data->timestamp)) {
                continue; // Skip if an alert was sent within the last 10 minutes
            }

            // Identify volume spikes
            $isSpike          = $data->last_volume > ($data->vma_15 * 2);
            $amplitudePercent = $this->calculateAmplitudePercent($data->high, $data->low); // Calculate percentage change

            if (!$isSpike || $amplitudePercent < $this->minPercent($crypto->id)) {
                continue;
            }

            $newSpikes[] = [
                'crypto'          => $crypto,
                'volume'          => $data->last_volume * $data->close,
                'vma'             => $data->vma_15 * $data->close,
                'price'           => $data->close,
                'amplitude'       => $amplitudePercent,
                'candle_color'    => $data->close > $data->open ? 'green' : 'red',
                'entry_score'     => $crypto->latest4h->meta['entry_score'] ?? 'N/A',
                'price_change_1h' => $crypto->latest1h->price_change,
                'price_change_4h' => $crypto->latest4h->price_change,
                'rsi_15m'         => $crypto->latest15m->meta['rsi'] ?? 'N/A',
                'rsi_1h'          => $crypto->latest1h->meta['rsi'] ?? 'N/A',
                'rsi_4h'          => $crypto->latest4h->meta['rsi'] ?? 'N/A',

                'timestamp'       => $data->timestamp,
            ];

            // Update the last_volume_alert timestamp
            $crypto->update(['last_volume_alert' => $data->timestamp]);
        }

        if (empty($newSpikes)) {
            $this->info('No new volume spikes detected.');
            return 0;
        }

        // Create alert message
        $message = "*New Alerts* 🚨\n";
        foreach ($newSpikes as $spike) {
            $message .= sprintf(
                "\n*#%s*\nPrice: %s USDT\n\n" .
                "Volume: %s USDT\nVMA: %s USDT\n\n" .
                "Entry Score: %s\n15m Amplitude: %s%%\n1h Price Change: %s%%\n4h Price Change: %s%%\n\n" .
                "15m RSI: %s\n1h RSI: %s\n4h RSI: %s\n" .
                "Time: %s\n",
                $spike['crypto']->symbol . ($spike['candle_color'] == 'green' ? ' 🟩' : ' 🟥'),
                number_format($spike['price'], 8),
                number_format($spike['volume'], 2),
                number_format($spike['vma'], 2),
                $spike['entry_score'],
                number_format($spike['amplitude'], 2),
                $spike['price_change_1h'],
                $spike['price_change_4h'],
                $spike['rsi_15m'],
                $spike['rsi_1h'],
                $spike['rsi_4h'],
                Carbon::parse($spike['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s') // Convert timestamp to SA timezone
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

    protected function minPercent($cryptoId)
    {
        if (in_array($cryptoId, $this->rank['top20'])) {
            return 1;
        }

        if (in_array($cryptoId, $this->rank['top50'])) {
            return 2;
        }

        if (in_array($cryptoId, $this->rank['top120'])) {
            return 3;
        }

        return 4;
    }
}
