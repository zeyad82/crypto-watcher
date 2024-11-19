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

    /**
     * Calculate Volume-Weighted EMA (VWEMA) as an array.
     *
     * @param array $prices Array of closing prices.
     * @param array $volumes Array of corresponding volumes.
     * @param int $period EMA period (e.g., 12, 26).
     * @return array Array of VWEMA values.
     */
    public static function VWEMAs(array $prices, array $volumes, int $period): array
    {
        $k       = 2 / ($period + 1); // Smoothing factor
        $vwemas  = [];
        $weights = array_map(fn($price, $volume) => $volume > 0 ? $price * $volume : 0, $prices, $volumes);

        // Initialize first VWEMA value with the weighted average
        $vwemas[0] = $volumes[0] > 0 ? $weights[0] / $volumes[0] : $prices[0];

        for ($i = 1; $i < count($prices); $i++) {
            // Skip calculations for zero volume and provide fallback
            $currentVolume = $volumes[$i] > 0 ? $volumes[$i] : 1; // Avoid zero division
            $currentWeight = $prices[$i] * $currentVolume;

            $vwemas[$i] = ($currentWeight * $k) + ($vwemas[$i - 1] * (1 - $k));
        }

        return $vwemas;
    }


    /**
     * Calculate Volume-Weighted MACD (VW-MACD).
     *
     * @param array $prices Array of closing prices.
     * @param array $volumes Array of corresponding volumes.
     * @return array
     */
    public static function VW_MACD(array $prices, array $volumes): array
    {
        // Step 1: Calculate VWEMAs for short and long periods as arrays
        $vwema12 = Self::VWEMAs($prices, $volumes, 12); // Array of 12-period VWEMA values
        $vwema26 = Self::VWEMAs($prices, $volumes, 26); // Array of 26-period VWEMA values

        // Step 2: Calculate the VW-MACD Line as the difference between VWEMA arrays
        $vwMacdLine = array_map(fn($short, $long) => $short - $long, $vwema12, $vwema26);

        // Step 3: Calculate the Signal Line as a 9-period EMA of the VW-MACD Line
        $signalLine = Self::EMAs($vwMacdLine, 9); // Array of Signal Line values

        // Step 4: Calculate Histogram as the difference between VW-MACD Line and Signal Line
        $histogram = array_map(fn($macd, $signal) => $macd - $signal, $vwMacdLine, $signalLine);

        // Step 5: Return the latest values of MACD Line,s Signal Line, and Histogram
        return [
            'macd_line'   => end($vwMacdLine),
            'signal_line' => end($signalLine),
            'histogram'   => end($histogram),
        ];
    }

    /**
     * Calculate RSI (Relative Strength Index).
     * 
     * @param array $prices Array of closing prices.
     * @param int $period RSI period.
     * @return float
     */
    public static function RSI(array $prices, int $period = 14): float
    {
        $gains = $losses = [];

        for ($i = 1; $i < count($prices); $i++) {
            $change = $prices[$i] - $prices[$i - 1];

            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0;
            } else {
                $gains[] = 0;
                $losses[] = abs($change);
            }
        }

        $averageGain = array_sum(array_slice($gains, -$period)) / $period;
        $averageLoss = array_sum(array_slice($losses, -$period)) / $period;

        if ($averageLoss == 0) {
            return 100; // Avoid division by zero, assume RSI is 100 (overbought).
        }

        $rs  = $averageGain / $averageLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return $rsi;
    }

}
