<?php

namespace App\Services;

class Calculate
{
    public static function EMA(array $values, int $period): float
    {
        if (count($values) < $period) {
            return 0;
        }

        $values = array_slice($values, -$period); // Use only the last $period values
        $k      = 2 / ($period + 1);
        $ema    = $values[0];

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

        $subset = array_slice($data, -$period); // Use only the last $period values
        return array_sum($subset) / $period;
    }

    public static function ATR(array $highs, array $lows, array $closes, int $period = 14): float
    {
        if (count($highs) < $period || count($lows) < $period || count($closes) < $period) {
            return 0;
        }

        $highs  = array_slice($highs, -$period); // Use only the last $period values
        $lows   = array_slice($lows, -$period);
        $closes = array_slice($closes, -($period + 1)); // Need one extra value for True Range calculation

        $tr = [];
        for ($i = 1; $i < count($highs); $i++) {
            $tr[] = max(
                $highs[$i] - $lows[$i],
                abs($highs[$i] - $closes[$i - 1]),
                abs($lows[$i] - $closes[$i - 1])
            );
        }

        return array_sum($tr) / $period;
    }

    public static function MACD(array $closingPrices): array
    {
        $closingPrices = array_slice($closingPrices, -26); // Use enough data for MACD calculation

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
        if (count($values) < $period) {
            return [];
        }

        $values = array_slice($values, -$period); // Use only the last $period values
        $k      = 2 / ($period + 1);
        $ema    = [$values[0]];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $k) + ($ema[$i - 1] * (1 - $k));
        }

        return $ema;
    }

    public static function VWEMAs(array $prices, array $volumes, int $period): array
    {
        if (count($prices) < $period || count($volumes) < $period) {
            return [];
        }

        $prices  = array_slice($prices, -$period); // Use only the last $period values
        $volumes = array_slice($volumes, -$period);

        $k       = 2 / ($period + 1);
        $vwemas  = [];
        $weights = array_map(fn($price, $volume) => $volume > 0 ? $price * $volume : 0, $prices, $volumes);

        $vwemas[0] = $volumes[0] > 0 ? $weights[0] / $volumes[0] : $prices[0];

        for ($i = 1; $i < count($prices); $i++) {
            $currentVolume = $volumes[$i] > 0 ? $volumes[$i] : 1;
            $currentWeight = $prices[$i] * $currentVolume;

            $vwemas[$i] = ($currentWeight * $k) + ($vwemas[$i - 1] * (1 - $k));
        }

        return $vwemas;
    }

    public static function VW_MACD(array $prices, array $volumes): array
    {
        $prices  = array_slice($prices, -26); // Use enough data for VW-MACD calculation
        $volumes = array_slice($volumes, -26);

        $vwema12 = Self::VWEMAs($prices, $volumes, 12);
        $vwema26 = Self::VWEMAs($prices, $volumes, 26);

        $vwMacdLine = array_map(fn($short, $long) => $short - $long, $vwema12, $vwema26);
        $signalLine = Self::EMAs($vwMacdLine, 9);
        $histogram  = array_map(fn($macd, $signal) => $macd - $signal, $vwMacdLine, $signalLine);

        return [
            'macd_line'   => end($vwMacdLine),
            'signal_line' => end($signalLine),
            'histogram'   => end($histogram),
        ];
    }

    public static function RSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period) {
            return 0;
        }

        $prices = array_slice($prices, -($period + 1)); // Use enough data for RSI calculation

        $gains = $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];

            if ($change > 0) {
                $gains[]  = $change;
                $losses[] = 0;
            } else {
                $gains[]  = 0;
                $losses[] = abs($change);
            }
        }

        $averageGain = array_sum(array_slice($gains, -$period)) / $period;
        $averageLoss = array_sum(array_slice($losses, -$period)) / $period;

        if ($averageLoss == 0) {
            return 100;
        }

        $rs  = $averageGain / $averageLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return $rsi;
    }
}
