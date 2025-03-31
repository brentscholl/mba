@props(['label', 'title', 'count', 'items', 'limit', 'expanded', 'toggle'])

@php
    $config = config("audit.$label");
    $description = $config['description'] ?? null;
    $whyItMatters = $config['why'] ?? null;
@endphp

<div class="border border-primary-200  rounded-lg p-4">
    <div class="font-semibold text-primary-900 mb-2 flex justify-between items-center">
        <span>
            {{ $title }}
        </span>
        <span class="text-sm text-primary-600">{{ number_format($count) }} suggestion{{ $count === 1 ? '' : 's' }}</span>
    </div>

    @if ($description)
        <div class="rounded bg-primary-100 p-2 mb-4 flex items-start space-x-2">
            <x-svg.info-circle class="w-5 h-5 text-primary-500"/>
            <div>
                <p class="text-sm text-gray-600 mb-2">
                    {{ $description }}
                </p>

                @if ($whyItMatters)
                    <p class="text-xs text-gray-500 italic mb-4">
                        <span class="text-gray-600">Why it matters:</span> {{ $whyItMatters }}
                    </p>
                @endif
            </div>
        </div>
    @endif

    @if ($count)
        <ul class="text-sm text-gray-700 space-y-2">
            @foreach ($expanded ? $items : array_slice($items, 0, $limit) as $item)
                <li class="border-b pb-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                        @foreach ($item as $key => $val)
                            <div>
                                <span class="font-medium">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                <span class="ml-1">
                                    {!! is_array($val)
                                        ? collect($val)->map(fn($v) => "<span class='inline-block'>$v</span>")->implode(', ')
                                        : e($val) !!}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($count > $limit)
            <button
                wire:click="{{ $toggle }}"
                class="mt-2 text-sm text-primary-700 hover:underline"
            >
                {{ $expanded ? 'Show less' : 'Show more' }}
            </button>
        @endif
    @else
        <p class="text-blue-600 italic text-sm">No suggestions found.</p>
    @endif
</div>
