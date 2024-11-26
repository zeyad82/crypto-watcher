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
    protected $description = 'Track cryptos with RSI below 30 and send alerts to Telegram.';
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
        $this->info('Tracking cryptos with RSI below 30...');

        // Define timeframes to check
        $timeframes = ['1m', '15m', '1h', '4h'];

        // Fetch the latest timestamp for each timeframe and crypto in a single query
        $cryptos = Crypto::with('latest1m', 'latest15m', 'latest1h', 'latest4h')
        ->orderByDesc('volume24')
        ->get();

        $alerts = [
            // '1m' => [],
            '15m' => [],
            '1h' => [],
            // '4h' => []
        ];

        /**
         * @var Crypto $crypto
         */
        foreach ($cryptos as $crypto) {

            foreach ($timeframes as $timeframe) {
                $data = $crypto->{'latest' . $timeframe};
                $rsi = $data->meta?->get('rsi');

                if ($rsi !== null && $rsi < 30) {
                    $priceChanges = $this->getPriceChanges($crypto);
                    $alerts[$timeframe][]     = [
                        'crypto'       => $crypto,
                        'rsi'          => $rsi,
                        'price'        => $data->close,
                        'price_change' => $priceChanges,
                        'timeframe'    => $timeframe,
                        'timestamp'    => $data->timestamp,
                    ];
                }
            }
        }


        foreach ($alerts as $timeframe => $timeframeAlerts) {
            if (!empty($timeframeAlerts)) {
                $this->sendToTelegram($this->formatAlerts($timeframe, $timeframeAlerts));
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
            $priceChanges = $alert['price_change'];
            $messages[]   = sprintf(
                "#%s - RSI: %s\nPrice: %s USDT\n15m Change: %s%%\n1h Change: %s%%\n4h Change: %s%%\nTime: %s",
                strtoupper($alert['crypto']->symbol),
                round($alert['rsi'], 2),
                round($alert['price'], 8),
                round($priceChanges['15m'], 2),
                round($priceChanges['1h'], 2),
                round($priceChanges['4h'], 2),
                Carbon::parse($alert['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s')
            );
        }

        return implode("\n\n", $messages);
    }

    /**
     * Fetch price changes for each crypto based on latest timestamps.
     *
     * @param int $cryptoId
     * @param \Illuminate\Support\Collection $latestTimestamps
     * @return array
     */
    protected function getPriceChanges(Crypto $crypto): array
    {
        $priceChanges = [];
        $timeframes = ['15m', '1h', '4h'];

        foreach ($timeframes as $timeframe) {
            $data = $crypto->{'latest' . $timeframe};

            $priceChanges[$timeframe] = $data ? $data->price_change : 0;
        }

        return $priceChanges;
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
