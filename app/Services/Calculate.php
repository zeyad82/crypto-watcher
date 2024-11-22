<?php

namespace App\Services;

class Calculate
{
    public static function EMA(array $values, int $period): float
    {
        if (count($values) < $period) {
            return 0;
        }

        $k   = 2 / ($period + 1);
        $ema = $values[0]; // Initialize with the first value

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

        $highs  = array_slice($highs, -$period);
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
        if (count($closingPrices) < 26) {
            return ['macd_line' => 0, 'signal_line' => 0, 'histogram' => 0];
        }

        $ema12 = self::EMAs($closingPrices, 12);
        $ema26 = self::EMAs($closingPrices, 26);

        $macdLine   = array_map(fn($e12, $e26) => $e12 - $e26, $ema12, $ema26);
        $signalLine = self::EMAs($macdLine, 9);
        $histogram  = array_map(fn($macd, $signal) => $macd - $signal, $macdLine, $signalLine);

        return [
            'macd_line'   => round(end($macdLine), 5),
            'signal_line' => round(end($signalLine), 5),
            'histogram'   => round(end($histogram), 5),
        ];
    }

    public static function EMAs(array $values, int $period): array
    {
        if (count($values) < $period) {
            return [];
        }

        $k   = 2 / ($period + 1);
        $ema = [$values[0]];

        for ($i = 1; $i < count($values); $i++) {
            $ema[$i] = ($values[$i] * $k) + ($ema[$i - 1] * (1 - $k));
        }

        return $ema;
    }

    public static function RSI(array $prices, int $period = 14): float
    {
        if (count($prices) < $period + 1) {
            return 0; // Not enough data to calculate RSI
        }

        // Calculate price changes
        $changes = [];
        for ($i = 1; $i < count($prices); $i++) {
            $changes[] = $prices[$i] - $prices[$i - 1];
        }

        // Separate gains and losses
        $gains  = [];
        $losses = [];
        foreach ($changes as $change) {
            if ($change > 0) {
                $gains[]  = $change;
                $losses[] = 0;
            } else {
                $gains[]  = 0;
                $losses[] = abs($change);
            }
        }

        // Calculate the initial average gain and loss using SMA
        $averageGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $averageLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        // Use the SMA values to calculate RSI
        for ($i = $period; $i < count($gains); $i++) {
            $averageGain = (($averageGain * ($period - 1)) + $gains[$i]) / $period;
            $averageLoss = (($averageLoss * ($period - 1)) + $losses[$i]) / $period;
        }

        // Avoid division by zero
        if ($averageLoss == 0) {
            return 100; // RSI is 100 if there's no loss
        }

        $rs  = $averageGain / $averageLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return round($rsi, 2); // Return RSI rounded to two decimal places
    }

    public static function ADX(array $highs, array $lows, array $closes, int $period = 14): array
    {
        if (count($highs) < $period + 1 || count($lows) < $period + 1 || count($closes) < $period + 1) {
            return ['adx' => 0, '+di' => 0, '-di' => 0]; // Not enough data
        }

        $trueRanges = [];
        $plusDM     = [];
        $minusDM    = [];

        // Step 1: Calculate TR, +DM, and -DM
        for ($i = 1; $i < count($highs); $i++) {
            $currentHigh   = $highs[$i];
            $currentLow    = $lows[$i];
            $previousClose = $closes[$i - 1];
            $previousHigh  = $highs[$i - 1];
            $previousLow   = $lows[$i - 1];

            $tr = max(
                $currentHigh - $currentLow,
                abs($currentHigh - $previousClose),
                abs($currentLow - $previousClose)
            );
            $trueRanges[] = $tr;

            $positiveDM = ($currentHigh - $previousHigh > $previousLow - $currentLow && $currentHigh - $previousHigh > 0)
            ? $currentHigh - $previousHigh : 0;
            $negativeDM = ($previousLow - $currentLow > $currentHigh - $previousHigh && $previousLow - $currentLow > 0)
            ? $previousLow - $currentLow : 0;

            $plusDM[]  = $positiveDM;
            $minusDM[] = $negativeDM;
        }

        // Step 2: Smooth TR, +DM, and -DM using Wilder's smoothing technique (EMAs)
        $smoothedTR      = end(self::EMAs($trueRanges, $period));
        $smoothedPlusDM  = end(self::EMAs($plusDM, $period));
        $smoothedMinusDM = end(self::EMAs($minusDM, $period));

        // Step 3: Calculate +DI, -DI
        $plusDI  = ($smoothedTR > 0) ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
        $minusDI = ($smoothedTR > 0) ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;

        // Step 4: Calculate DX
        $dx = ($plusDI + $minusDI > 0) ? abs($plusDI - $minusDI) / ($plusDI + $minusDI) * 100 : 0;

        // Step 5: Smooth DX to calculate ADX
        $adx = self::EMAs([$dx], $period);

        return [
            'adx' => round(end($adx), 2),
            '+di' => round($plusDI, 2),
            '-di' => round($minusDI, 2),
        ];
    }

}
