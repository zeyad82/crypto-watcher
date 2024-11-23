<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
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
        $allCryptos = Crypto::orderByDesc('volume24')->get();

        $this->rank = [
            'top20'  => $allCryptos->take(20)->pluck('id')->toArray(),
            'top50'  => $allCryptos->slice(20, 30)->pluck('id')->toArray(),
            'top120' => $allCryptos->slice(50, 70)->pluck('id')->toArray(),
        ];

        // Fetch recent volume data
        $recentData = VolumeData::with('crypto')
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

        $newSpikes = [];

        /**
         * @var VolumeData $data
         */
        foreach ($recentData as $data) {
            $crypto = $data->crypto;

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
                'crypto'       => $crypto,
                'volume'       => $data->last_volume * $data->close,
                'vma'          => $data->vma_15 * $data->close,
                'price'        => $data->close,
                'amplitude'    => $amplitudePercent,
                'candle_color' => $data->close > $data->open ? 'green' : 'red',
                'timestamp'    => $data->timestamp,
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
                "\n*#%s*\nVolume: %s USDT\nAmplitude: %s%%\nVMA: %s USDT\nPrice: %s USDT\nTime: %s\n",
                $spike['crypto']->symbol . ($spike['candle_color'] == 'green' ? ' 🟩' : ' 🟥'),
                number_format($spike['volume'], 2),
                number_format($spike['amplitude'], 2),
                number_format($spike['vma'], 2),
                number_format($spike['price'], 8),
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
