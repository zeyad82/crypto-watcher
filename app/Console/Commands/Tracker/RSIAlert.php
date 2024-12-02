<?php

namespace App\Console\Commands\Tracker;

use App\Models\Crypto;
use App\Models\VolumeData;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
        // Define timeframes to check
        $timeframes = ['1m', '15m', '1h'];

        // Fetch the latest timestamp for each timeframe and crypto in a single query
        $cryptos = Crypto::with('latest1m', 'latest15m', 'latest1h')
            ->orderByDesc('volume24')
            ->take(100)
            ->get();
        
        $alerts = [];

        /**
         * @var Crypto $crypto
         */
        foreach ($cryptos as $crypto) {
            $overSold = [];

            foreach ($timeframes as $timeframe) {
                $data = $crypto->{'latest' . $timeframe};

                $rsi = $data->meta?->get('rsi');

                if ($rsi !== null && $rsi < 40) {
                    $overSold[] = true;
                }
            }

            if (count($overSold) == 3 && !Cache::has("alerted_crypto_{$crypto->id}")) {
                $metrics  = $this->getMetrics($crypto);
                $alerts[] = [
                    'crypto'    => $crypto,
                    'price'     => $crypto->latest1m->close,
                    'metrics'   => $metrics,

                    '1m_ema_trend'    => $this->getTrend($crypto->latest1m),
                    '15m_ema_trend'    => $this->getTrend($crypto->latest15m),
                    '1h_ema_trend'     => $this->getTrend($crypto->latest1h),

                    'timestamp' => $data->timestamp,
                ];

                Cache::put("alerted_crypto_{$crypto->id}", true, now()->addMinutes(30));
            }

        }

        foreach ($alerts as $alert) {
            $this->sendToTelegram($this->formatAlerts($alert));
        }

        $this->info('RSI alerts sent to Telegram.');
        return 0;
    }

    /**
     * Format alerts for Telegram for a specific timeframe.
     *
     * @param array $alerts
     * @return string
     */
    protected function formatAlerts(array $alert): string
    {
        $msg = sprintf(
            "#%s \nPrice: %s USDT\n\n" .
            "RSI 1m: %s\nRSI 15m: %s\nRSI 1h: %s\n\n" .
            "1m EMA : %s\n15m EMA: %s\n1h EMA: %s\n\n" .
            "1m Change: %s%%\n15m Change: %s%%\n1h Change: %s%%\nTime: %s \n\n",
            strtoupper($alert['crypto']->symbol),
            round($alert['price'], 8),

            round($alert['metrics']['RSIs']['1m'], 2),
            round($alert['metrics']['RSIs']['15m'], 2),
            round($alert['metrics']['RSIs']['1h'], 2),

            $alert['1m_ema_trend'],
            $alert['15m_ema_trend'],
            $alert['1h_ema_trend'],

            round($alert['metrics']['price_changes']['1m'], 2),
            round($alert['metrics']['price_changes']['15m'], 2),
            round($alert['metrics']['price_changes']['1h'], 2),
            Carbon::parse($alert['timestamp'])->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s')
        );

        return $msg;
    }

    /**
     * Fetch price changes for each crypto based on latest timestamps.
     *
     * @param int $cryptoId
     * @param \Illuminate\Support\Collection $latestTimestamps
     * @return array
     */
    protected function getMetrics(Crypto $crypto): array
    {
        $priceChanges = [];
        $RSIs         = [];
        $timeframes   = ['1m', '15m', '1h'];

        foreach ($timeframes as $timeframe) {
            $data = $crypto->{'latest' . $timeframe};

            $priceChanges[$timeframe] = $data->price_change ?? 0;
            $RSIs[$timeframe]         = $data->meta->get('rsi') ?? 0;
        }

        return [
            'price_changes' => $priceChanges,
            'RSIs'          => $RSIs,
        ];
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

    protected function getTrend(VolumeData $data)
    {
        if (!$data->price_ema_15 || !$data->price_ema_25) {
            return 'empty';
        }

        if ($data->price_ema_15 > $data->price_ema_25) {
            return 'bullish';
        }

        if ($data->price_ema_15 < $data->price_ema_25) {
            return 'bearish';
        }
    }
}
