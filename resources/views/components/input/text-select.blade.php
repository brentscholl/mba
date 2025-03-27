@props([
    'type' => "text",
    'label' => "",
    'required' => false,
    'placeholder' => "",
    'lead' => "",
    'subtext' => '',
    'optional' => false,
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
			<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
				<span class="text-gray-500 sm:text-sm">{{ $lead }}</span>
			</div>
		@endif
		<input
			{{ $attributes->whereStartsWith('wire:model') }}
			type="{{ $type }}"
			placeholder="{{ $placeholder }}"
			id="{{ $attributes->whereStartsWith('wire:model')->first() }}"
			@if($required) required @endif
			onkeydown="return event.key !== 'Enter';"

			@error($attributes->whereStartsWith('wire:model')->first())
				class="z-20 form-input-container__input form-input-container__input--text-select form-input-container__input--text-select--has-error @if($lead) form-input-container__input--text-select--has-lead @endif"
				aria-invalid="true"
				aria-describedby="{{ $attributes->whereStartsWith('wire:model')->first() }}-error"
			@else
				class="z-20 form-input-container__input form-input-container__input--text-select @if($lead) form-input-container__input--text-select--has-lead @endif"
			@endif
		>
		<div class="flex items-center">
			<select
				{{ $select->attributes->whereStartsWith('wire:model') }}
				id="{{ $select->attributes->whereStartsWith('wire:model')->first() }}"
				class="z-10 h-full border rounded-l-none border-gray-300 rounded-md bg-gray-50 border-l-0 py-0 pl-2 pr-7 text-gray-500
				focus:border-secondary-500 focus:ring-secondary-500 sm:text-sm
				"
			>
				{{ $select }}
			</select>
		</div>
		@error($attributes->whereStartsWith('wire:model')->first())
		<div wire:key="error_svg_{{ $attributes->whereStartsWith('wire:model')->first() }}"
			class="form-input-container__input__icon--has-error"
		>
			<x-svg.error/>
		</div>
		@enderror
	</div>
	@if($subtext)
		<p class="mt-2 text-sm text-gray-500" id="email-description">{{ $subtext }}</p>
	@endif
	@error($attributes->whereStartsWith('wire:model')->first())
	<p wire:key="error_{{ $attributes->whereStartsWith('wire:model')->first() }}"
		class="form-input-container__input__error-message"
		id="error-{{ $attributes->whereStartsWith('wire:model')->first() }}"
	>
		{{ $message }}
	</p>
	@enderror
</div>

