<div wire:poll.10s class="overflow-auto">
    <table class="table-auto w-full border-collapse border border-gray-200">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('symbol')">
                    Symbol
                    @if($sortColumn === 'symbol')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('latest_price_1m')">
                    Price
                    @if($sortColumn === 'latest_price_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('rsi_1m')">
                    1m RSI
                    @if($sortColumn === 'rsi_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('rsi_15m')">
                    15m RSI
                    @if($sortColumn === 'rsi_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('volume_1m')">
                    1m Volume
                    @if($sortColumn === 'volume_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('volume_15m')">
                    15m Volume
                    @if($sortColumn === 'volume_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('price_change_1m')">
                    1m Price Change (%)
                    @if($sortColumn === 'price_change_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('price_change_15m')">
                    15m Price Change (%)
                    @if($sortColumn === 'price_change_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema15_1m')">
                    1m EMA 15
                    @if($sortColumn === 'ema15_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema25_1m')">
                    1m EMA 25
                    @if($sortColumn === 'ema25_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema50_1m')">
                    1m EMA 50
                    @if($sortColumn === 'ema50_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema15_15m')">
                    15m EMA 15
                    @if($sortColumn === 'ema15_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema25_15m')">
                    15m EMA 25
                    @if($sortColumn === 'ema25_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('ema50_15m')">
                    15m EMA 50
                    @if($sortColumn === 'ema50_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('adx_1m')">
                    1m ADX
                    @if($sortColumn === 'adx_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('+di_1m')">
                    1m DI+
                    @if($sortColumn === '+di_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('-di_1m')">
                    1m DI-
                    @if($sortColumn === '-di_1m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>

                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('adx_15m')">
                    15m ADX
                    @if($sortColumn === 'adx_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('+di_15m')">
                    15m DI+
                    @if($sortColumn === '+di_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
                <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('-di_15m')">
                    15m DI-
                    @if($sortColumn === '-di_15m')
                        @if($sortDirection === 'asc') ↑ @else ↓ @endif
                    @endif
                </th>
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
