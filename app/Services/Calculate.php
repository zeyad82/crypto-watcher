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

        // Format the results to ensure compatibility
        return [
            'macd_line'   => self::formatNumber(end($macdLine)),
            'signal_line' => self::formatNumber(end($signalLine)),
            'histogram'   => self::formatNumber(end($histogram)),
        ];
    }

    public static function EMAs(array $values, int $period): array
    {
        if (count($values) < $period) {
            return [];
        }

        $k   = self::formatNumber(2 / ($period + 1)); // Convert constant to formatted string
        $ema = [self::formatNumber($values[0])]; // Initialize with the first value as a sanitized string

        for ($i = 1; $i < count($values); $i++) {
            // Sanitize inputs
            $currentValue = self::formatNumber($values[$i]);
            $previousEma  = self::formatNumber($ema[$i - 1]);

            // Perform EMA calculation
            $ema[$i] = bcadd(
                bcmul($currentValue, $k, 10),
                bcmul($previousEma, self::formatNumber(1 - (2 / ($period + 1))), 10),
                10
            );
        }

        return array_map('floatval', $ema); // Convert the final EMA values back to floats
    }

    public static function formatNumber($value): string
    {
        if (!is_numeric($value)) {
            return '0'; // Return 0 for non-numeric inputs
        }

        // Convert scientific notation to decimal format
        if (stripos((string) $value, 'e') !== false) {
            $value = sprintf('%.20f', (float) $value);
        }

        // Trim trailing zeros and return
        return rtrim($value, '0') ?: '0';
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
            return ['adx' => 0, '+di' => 0, '-di' => 0];
        }

        $trueRanges = [];
        $plusDM     = [];
        $minusDM    = [];
        $dxHistory  = [];

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

        // Step 2: Smooth TR, +DM, and -DM
        $smoothedTRValues      = self::EMAs($trueRanges, $period);
        $smoothedPlusDMValues  = self::EMAs($plusDM, $period);
        $smoothedMinusDMValues = self::EMAs($minusDM, $period);

        if (empty($smoothedTRValues) || empty($smoothedPlusDMValues) || empty($smoothedMinusDMValues)) {
            return ['adx' => 0, '+di' => 0, '-di' => 0];
        }

        $smoothedTR      = end($smoothedTRValues);
        $smoothedPlusDM  = end($smoothedPlusDMValues);
        $smoothedMinusDM = end($smoothedMinusDMValues);

        // Step 3: Calculate +DI, -DI
        $plusDI  = ($smoothedTR > 0) ? ($smoothedPlusDM / $smoothedTR) * 100 : 0;
        $minusDI = ($smoothedTR > 0) ? ($smoothedMinusDM / $smoothedTR) * 100 : 0;

        // Step 4: Calculate DX
        for ($i = 0; $i < count($smoothedPlusDMValues); $i++) {
            $currentPlusDI  = ($smoothedTRValues[$i] > 0) ? ($smoothedPlusDMValues[$i] / $smoothedTRValues[$i]) * 100 : 0;
            $currentMinusDI = ($smoothedTRValues[$i] > 0) ? ($smoothedMinusDMValues[$i] / $smoothedTRValues[$i]) * 100 : 0;
            $dx             = ($currentPlusDI + $currentMinusDI > 0) ? abs($currentPlusDI - $currentMinusDI) / ($currentPlusDI + $currentMinusDI) * 100 : 0;
            $dxHistory[]    = $dx;
        }

        if (count($dxHistory) < $period) {
            return ['adx' => 0, '+di' => round($plusDI, 2), '-di' => round($minusDI, 2)];
        }

        // Step 5: Smooth DX to calculate ADX
        $adxValues = self::EMAs($dxHistory, $period);

        if (empty($adxValues)) {
            return ['adx' => 0, '+di' => round($plusDI, 2), '-di' => round($minusDI, 2)];
        }

        return [
            'adx' => round(end($adxValues), 2),
            '+di' => round($plusDI, 2),
            '-di' => round($minusDI, 2),
        ];
    }

}
