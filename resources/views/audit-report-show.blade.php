@extends('layouts.app')

@section('content')

@php
    $config = config("audit." . $report->key);
    $description = $config['description'] ?? null;
    $whyItMatters = $config['why'] ?? null;
@endphp
    <div class="max-w-6xl mx-auto mt-12 space-y-6 pb-12">
        <div class="bg-white p-6 rounded-xl shadow">
            <a href="{{ route('files.show', $report->file_id) }}" class="text-xl text-primary-800 font-semibold mb-4 flex items-center space-x-2 py-2 px-4 bg-primary-100 rounded hover:bg-primary-200 transition">
                <x-svg.file class="w-5 h-5"/>
                <span>{{ $report->file->original_filename }}</span>
            </a>

            <div class="border border-gray-200 rounded-lg p-4">
                <div class="font-semibold text-gray-800 mb-2 flex justify-between items-center">

                    <h2 class="text-xl text-primary-700 font-semibold flex items-center space-x-2">
                        {{ $report->title }}
                    </h2>
{{--                    <span class="text-sm text-gray-500">{{ number_format($count) }} issue{{ $count === 1 ? '' : 's' }}</span>--}}
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

                @livewire('audit-report-items', ['report' => $report])
            </div>

        </div>
    </div>
@endsection
