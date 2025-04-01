<div class="text-sm text-gray-700 space-y-2">
    @forelse ($items as $item)
        <div class="border-b pb-2 flex justify-between items-start gap-4">
            <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
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
            </div>

            <a href="{{ route('audit.report.show-item', ['report' => $report->id, 'item' => $item->id]) }}"
                class="shrink-0 whitespace-nowrap text-sm text-primary-600 hover:underline flex items-center space-x-1">
                <span>View Issue</span>
            </a>
        </div>
    @empty
        <p class="text-sm text-gray-500 italic">No audit items found.</p>
    @endforelse

    <div>
        {{ $items->links() }}
    </div>
</div>
