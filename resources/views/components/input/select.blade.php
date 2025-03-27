@props([
    'label' => "",
    'required' => false,
    'lead' => "",
    'subtext' => '',
    'optional' => false,
    'disableErrors' => false,
])
<div class="{{ $attributes->get('class') }}">
    <div class="flex justify-between">
        <label for="{{ $attributes->whereStartsWith('wire:model')->first() }}" class="{{ $required ? 'required' : '' }}">{!! $label !!}</label>
        @if($optional)
            <span class="text-xs text-gray-500" id="email-optional">Optional</span>
        @endif
    </div>
    <div class="form-input-container">
        @if($lead)
            <span class="form-input-container__lead">
              {{ $lead }}
            </span>
        @endif
        <select
            onkeydown="return event.key !== 'Enter';"
            {{ $attributes->whereStartsWith('wire:model') }}
            id="{{ $attributes->whereStartsWith('wire:model')->first() }}"
            @if($required) required @endif
        @if($disableErrors)
            class="form-input-container__input @if($lead) form-input-container__input--has-lead @endif"
        @else
            @error($attributes->whereStartsWith('wire:model')->first())
            class="form-input-container__input form-input-container__input--has-error @if($lead) form-input-container__input--has-lead @endif"
            aria-invalid="true"
            aria-describedby="{{ $attributes->whereStartsWith('wire:model')->first() }}-error"
            @else
                class="form-input-container__input @if($lead) form-input-container__input--has-lead @endif"
            @endif
        @endif
        >
            {{ $slot }}
        </select>
    </div>
    @if($subtext)
        <p class="mt-1 text-xs text-gray-500">{!! $subtext !!}</p>
    @endif
    @if(!$disableErrors)
        @error($attributes->whereStartsWith('wire:model')->first())
        <p wire:key="error_{{ $attributes->whereStartsWith('wire:model')->first() }}"
            class="form-input-container__input__error-message"
            id="error-{{ $attributes->whereStartsWith('wire:model')->first() }}">
            {{ $message }}
        </p>
        @enderror
    @endif
</div>
