@props(['label', 'title', 'count', 'items', 'expanded', 'toggle'])

<div class="border border-gray-200 rounded-lg p-4">
    <div class="font-semibold text-gray-800 mb-2 flex justify-between items-center">
        <span>{{ $title }}</span>
        <span class="text-sm text-gray-500">{{ number_format($count) }} issue{{ $count === 1 ? '' : 's' }}</span>
    </div>

    @if ($count)
        <ul class="text-sm text-gray-700 space-y-2">
            @foreach ($expanded ? $items : array_slice($items, 0, 5) as $item)
                <li class="border-b pb-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                        @foreach ($item as $key => $val)
                            <div>
                                <span class="font-medium">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                <span class="ml-1">{{ is_array($val) ? json_encode($val) : $val }}</span>
                            </div>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($count > 5)
            <button
                wire:click="{{ $toggle }}"
                class="mt-2 text-sm text-blue-600 hover:underline"
            >
                {{ $expanded ? 'Show less' : 'Show more' }}
            </button>
        @endif
    @else
        <p class="text-gray-500 italic text-sm">No issues found.</p>
    @endif
</div>
