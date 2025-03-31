@extends('layouts.app')

@section('content')
    <div class="max-w-6xl mx-auto mt-12 space-y-6 pb-12">
        <div class="bg-white p-6 rounded-xl shadow">
            <h2 class="text-xl font-semibold mb-4 flex items-center space-x-2 py-2 px-4 bg-primary-100 rounded">
                <x-svg.search class="w-5 h-5"/>
                <span>{{ $report->title }}</span>
            </h2>

            <p class="text-sm text-gray-600 mb-4">
                File: <a href="{{ route('files.show', $report->file_id) }}" class="text-primary-600 hover:underline">{{ $report->file->original_filename }}</a>
            </p>

            @livewire('audit-report-items', ['report' => $report])
        </div>
    </div>
@endsection
