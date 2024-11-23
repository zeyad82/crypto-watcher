<div wire:poll.10s class="overflow-auto">
    <table class="table-auto w-full border-collapse border border-gray-200">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-200 px-4 py-2">Symbol</th>
                <th class="border border-gray-200 px-4 py-2">Price</th>

                <th class="border border-gray-200 px-4 py-2">1m RSI</th>
                <th class="border border-gray-200 px-4 py-2">15m RSI</th>

                <th class="border border-gray-200 px-4 py-2">1m Volume</th>
                <th class="border border-gray-200 px-4 py-2">15m Volume</th>

                <th class="border border-gray-200 px-4 py-2">1m Price Change (%)</th>
                <th class="border border-gray-200 px-4 py-2">15m Price Change (%)</th>

                <th class="border border-gray-200 px-4 py-2">1m EMA 15</th>
                <th class="border border-gray-200 px-4 py-2">1m EMA 25</th>
                <th class="border border-gray-200 px-4 py-2">1m EMA 50</th>
                <th class="border border-gray-200 px-4 py-2">15m EMA 15</th>
                <th class="border border-gray-200 px-4 py-2">15m EMA 25</th>
                <th class="border border-gray-200 px-4 py-2">15m EMA 50</th>

                <th class="border border-gray-200 px-4 py-2">1m ADX</th>
                <th class="border border-gray-200 px-4 py-2">1m +DI</th>
                <th class="border border-gray-200 px-4 py-2">1m -DI</th>

                <th class="border border-gray-200 px-4 py-2">15m ADX</th>
                <th class="border border-gray-200 px-4 py-2">15m +DI</th>
                <th class="border border-gray-200 px-4 py-2">15m -DI</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cryptoData as $crypto)
                <tr>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['symbol'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['latest_price_1m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['rsi_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['rsi_15m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['volume_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['volume_15m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['price_change_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['price_change_15m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema15_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema25_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema50_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema15_15m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema25_15m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['ema50_15m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['adx_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['+di_1m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['-di_1m'] }}</td>

                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['adx_15m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['+di_15m'] }}</td>
                    <td class="border border-gray-200 px-4 py-2">{{ $crypto['-di_15m'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="21" class="text-center py-4">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
