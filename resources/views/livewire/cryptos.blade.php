<div wire:poll.11s class="overflow-auto max-h-screen border border-gray-200">
    <table class="table-auto w-full border-collapse border border-gray-200">
        <thead class="sticky top-0 bg-gray-100 z-10 shadow">
            <tr class="bg-gray-100">
                @foreach (Static::columns() as $key => $label)
                    <th class="border border-gray-200 px-4 py-2 cursor-pointer" wire:click="sortBy('{{$key}}')">
                        {{$label}}
                        @if($sortColumn === $key)
                            @if($sortDirection === 'asc') ↑ @else ↓ @endif
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($cryptoData as $crypto)
                <tr>
                    @foreach (Static::columns() as $key => $label)
                        <td class="border border-gray-200 px-4 py-2">{{ $crypto[$key] }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="21" class="text-center py-4">No data available</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
