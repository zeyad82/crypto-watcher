<?php

namespace App\Services;


class Calculate
{
    public static function EMA(array $values, int $period): float
    {
        $k   = 2 / ($period + 1);
        $ema = $values[0];

        foreach ($values as $index => $value) {
            if ($index === 0) {
                continue;
            }
            $ema = ($value * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    public static function MA(array $data, int $period): float
    {
        if (count($data) < $period) {
            return 0;
        }

        $subset = array_slice($data, -$period);
        return array_sum($subset) / $period;
    }

    public static function ATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        $tr = [];
        for ($i = 1; $i < count($highs); $i++) {
            $tr[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }

        return array_sum(array_slice($tr, -$period)) / $period;
    }

    public static function MACD(array $closingPrices): array
    {
        $ema12 = Self::EMAs($closingPrices, 12);
        $ema26 = Self::EMAs($closingPrices, 26);

        $macdLine   = array_map(fn($e12, $e26) => $e12 - $e26, $ema12, $ema26);
        $signalLine = Self::EMAs($macdLine, 9);
        $histogram  = array_map(fn($macd, $signal) => $macd - $signal, $macdLine, $signalLine);

        return [
            'macd_line'   => end($macdLine),
            'signal_line' => end($signalLine),
            'histogram'   => end($histogram),
        ];
    }

    public static function EMAs(array $values, int $period): array
    {
        $k      = 2 / ($period + 1);
        $ema    = [];
        $ema[0] = $values[0];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $k) + ($ema[$i - 1] * (1 - $k));
        }

        return $ema;
    }
}
