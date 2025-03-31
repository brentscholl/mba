<div class="space-y-6">
    @forelse ($items as $item)
        <div class="border border-gray-200 rounded p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                @foreach ($item->data as $key => $val)
                    <div>
                        <span class="font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                        <span class="ml-1">
                            @if (is_array($val))
                                {{ collect($val)->join(', ') }}
                            @else
                                {{ $val }}
                            @endif
                        </span>
                    </div>
                @endforeach

                @if ($item->reasoning)
                    <div class="col-span-full text-sm text-gray-600 italic">
                        <span class="text-gray-800 font-medium">Reasoning:</span> {{ $item->reasoning }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 italic">No audit items found.</p>
    @endforelse

    <div>
        {{ $items->links() }}
    </div>
</div>
