@props(['label', 'title', 'count', 'items', 'limit', 'toggle', 'reportId'])

@php
    $config = config("audit.$label");
    $description = $config['description'] ?? null;
    $whyItMatters = $config['why'] ?? null;
@endphp

<div class="border border-gray-200 rounded-lg p-4">
    <div class="font-semibold text-gray-800 mb-2 flex justify-between items-center">
        <a
            href="{{ route('audit.report.show', ['report' => $reportId]) }}"
            class="hover:underline text-primary-700"
        >
            {{ $title }}
        </a>
        <span class="text-sm text-gray-500">{{ number_format($count) }} issue{{ $count === 1 ? '' : 's' }}</span>
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
            @foreach (array_slice($items, 0, $limit) as $item)
                <li class="border-b pb-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-700">
                        @foreach ($item['data'] as $key => $val)
                        <div>
                                <span class="font-medium text-gray-800">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                <span class="ml-1">
                                    @if (is_array($val))
                                        {{ collect($val)->implode(', ') }}
                                    @else
                                        {{ $val }}
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($count > $limit)
            <div class="flex space-x-4 mt-3">
                <a
                    href="{{ route('audit.report.show', ['report' => $reportId]) }}"
                    class="text-sm text-primary-600 hover:underline flex items-center space-x-1"
                >
                    <x-svg.arrow-right class="w-4 h-4"/>
                    <span>View Full Report</span>
                </a>
            </div>
        @endif
    @else
        <p class="text-gray-500 italic text-sm">No issues found.</p>
    @endif
</div>
