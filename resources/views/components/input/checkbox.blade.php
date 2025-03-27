@props([
    // NOTE: Livewire model must be an array ie. public $checkboxes = [];
    'label' => '',
    'description' => null,
    'value' => '',
    'type' => 'checkbox',
    'uid' => rand(),
    'id' => '',
])
<div class="relative flex items-start">
    <div class="flex items-center h-5">
        <input
                wire:key="checkbox_{{ $attributes->whereStartsWith('wire:model')->first() }}_{{$uid}}"
            {{ $attributes->whereStartsWith('wire:model') }}
            {{ $attributes->whereStartsWith('x') }}
{{--            name="{{ $attributes->whereStartsWith('wire:model')->first() }}"--}}
            value="{{ $value }}"
            @if($id)
                id="{{ $id }}"
            @else
                id="checkbox-{{ $attributes->whereStartsWith('wire:model')->first() }}-{{ $uid }}"
            @endif
            aria-describedby="checkbox-{{ $attributes->whereStartsWith('wire:model')->first() }}-description_{{$uid}}"
            type="{{ $type }}"
            class="focus:ring-secondary-500 h-5 w-5 text-secondary-600 border border-gray-300 rounded">
    </div>
    <div class="ml-3 text-sm">
        <label
                @if($id)
                    for="{{ $id }}"
                @else
                    for="checkbox-{{ $attributes->whereStartsWith('wire:model')->first() }}-{{ $uid }}"
                @endif
                class="font-medium text-gray-700">{{ $label }}</label>
        @if($description)
            <p id="checkbox-{{ $attributes->whereStartsWith('wire:model')->first() }}-description_{{$uid}}" class="text-gray-500">{{ $description }}</p>
        @endif
    </div>
</div>
