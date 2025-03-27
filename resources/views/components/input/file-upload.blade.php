@props([
    'label' => "",
    'acceptedFileTypes' => [],
    'maxFileSize' => '100MB',
])

@php
    $accepted = collect($acceptedFileTypes)->implode(',');
    $wireModel = $attributes->whereStartsWith('wire:model')->first();
@endphp

@push('styles')
    <link href="https://unpkg.com/filepond/dist/filepond.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://unpkg.com/filepond/dist/filepond.min.js"></script>
@endpush

<div class="{{ $attributes->get('class') }}"
    wire:ignore
    wire:key="filepond_{{ $wireModel }}"
    x-data
    x-init="
        let pond = initializeFileUploader($refs.input, $wire, {
            acceptedFileTypes: '{{ $accepted }}'.split(','),
            multiple: {{ isset($attributes['multiple']) ? 'true' : 'false' }},
            wireModel: '{{ str_replace('wire:model=', '', $wireModel) }}',
        });

        $wire.on('pondReset', () => {
            pond.removeFiles();
        });
    "
>
    @if ($label)
        <label for="{{ $wireModel }}">{{ $label }}</label>
    @endif

    <input
        {{ $attributes->whereStartsWith('wire:model') }}
        type="file"
        x-ref="input"
        data-allow-reorder="false"
        data-max-file-size="{{ $maxFileSize }}"
        data-max-files="10"
        {{ isset($attributes['multiple']) ? 'multiple' : '' }}
        accept="{{ $accepted }}"
    >

    @error($wireModel)
    <p class="text-red-600 text-sm mt-2">{{ $message }}</p>
    @enderror
</div>
