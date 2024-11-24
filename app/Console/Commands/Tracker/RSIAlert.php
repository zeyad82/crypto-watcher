<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class RSIAlert extends Command
{
    protected $signature   = 'tracker:rsi-alert';
    protected $description = 'Track cryptos with RSI below 35 and send alerts to Telegram.';
    protected $telegramBotToken;
    protected $telegramChatId;
    protected $httpClient;

    public function __construct()
    {
        parent::__construct();

        $this->telegramBotToken = config('volume.telegram.token'); // Telegram Bot Token
        $this->telegramChatId   = config('volume.telegram.rsi_chat_id'); // Telegram Chat ID
        $this->httpClient       = new Client(); // HTTP Client for Telegram API
    }

    public function handle()
    {
        $this->info('Tracking cryptos with RSI below 40...');

        // Define timeframes to check
        $timeframes = ['1m', '15m', '1h', '4h'];

        foreach ($timeframes as $timeframe) {
            // Fetch volume data for all cryptos, sorted by `volume24`
            $recentData = VolumeData::with('crypto')
                ->where('timeframe', $timeframe)
                ->join('cryptos', 'volume_data.crypto_id', '=', 'cryptos.id')
                ->orderByDesc('cryptos.volume24') // Sort by `volume24`
                ->joinSub(
                    VolumeData::select('crypto_id', VolumeData::raw('MAX(timestamp) AS latest_timestamp'))
                        ->where('timeframe', $timeframe)
                        ->groupBy('crypto_id'),
                    'latest',
                    function ($join) {
                        $join->on('volume_data.crypto_id', '=', 'latest.crypto_id')
                            ->on('volume_data.timestamp', '=', 'latest.latest_timestamp');
                    }
                )
                ->get();

            $alerts = [];

            foreach ($recentData as $data) {
                $rsi = $data->meta?->get('rsi');

                if ($rsi !== null && $rsi < 35) {
                    $alerts[] = [
                        'crypto'    => $data->crypto,
                        'rsi'       => $rsi,
                        'price'     => $data->close,
                        'timestamp' => $data->timestamp,
                    ];
                }
            }

            if (!empty($alerts)) {
                $this->sendToTelegram($this->formatAlerts($timeframe, $alerts));
            }
        }

        $this->info('RSI alerts sent to Telegram.');
        return 0;
    }

    /**
     * Format alerts for Telegram for a specific timeframe.
     *
     * @param string $timeframe
     * @param array $alerts
     * @return string
     */
    protected function formatAlerts(string $timeframe, array $alerts): string
    {
        $header   = sprintf("*RSI %s*", strtoupper($timeframe));
        $messages = [$header];

        foreach ($alerts as $alert) {
            $messages[] = sprintf(
                "#%s - RSI: %s\nPrice: %s USDT\nTime: %s",
                strtoupper($alert['crypto']->symbol),
                round($alert['rsi'], 2),
                round($alert['price'], 8),
                Carbon::parse($alert['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s')
            );
        }

        return implode("\n\n", $messages);
    }

    /**
     * Send alerts to Telegram.
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
