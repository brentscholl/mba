@props([
    'label' => "",
    'required' => false,
    'placeholder' => "",
    'subtext' => '',
    'optional' => false,
    'rows' => 3,
    'fullHeight' => false,
    'disableErrors' => false
])
<div class="{{ $attributes->get('class') }}">
	<div class="flex justify-between">
		<label for="{{ $attributes->whereStartsWith('wire:model')->first() }}" class="{{ $required ? 'required' : '' }}">{!! $label !!}</label>
		@if($optional)
			<span class="text-xs text-gray-500" id="email-optional">Optional</span>
		@endif
	</div>
	<div class="form-input-container @if($fullHeight) h-full @endif">
		<textarea rows="{{ $rows }}"
			{{ $attributes->whereStartsWith('wire:model') }}
			placeholder="{{ $placeholder }}"
			id="{{ $attributes->whereStartsWith('wire:model')->first() }}"
			@if($required) required @endif
			@if($disableErrors)
				class="form-input-container__input @if($fullHeight) h-full @endif"
			@else
				@error($attributes->whereStartsWith('wire:model')->first())
					class="form-input-container__input form-input-container__input--has-error @if($fullHeight) h-full @endif"
					aria-invalid="true"
					aria-describedby="{{ $attributes->whereStartsWith('wire:model')->first() }}-error"
				@else
					class="form-input-container__input @if($fullHeight) h-full @endif"
				@endif
			@endif
		></textarea>
		@if(!$disableErrors)
		@error($attributes->whereStartsWith('wire:model')->first())
			<div wire:key="error_svg_{{ $attributes->whereStartsWith('wire:model')->first() }}"
				class="form-input-container__input__icon--has-error"
			>
				<x-svg.error/>
			</div>
			@enderror
		@endif
	</div>
	@if($subtext)
		<p class="mt-2 text-sm text-gray-500" id="email-description">{{ $subtext }}</p>
	@endif
	@if(!$disableErrors)
		@error($attributes->whereStartsWith('wire:model')->first())
		<p wire:key="error_{{ $attributes->whereStartsWith('wire:model')->first() }}"
			class="form-input-container__input__error-message"
			id="error-{{ $attributes->whereStartsWith('wire:model')->first() }}"
		>
			{{ $message }}
		</p>
		@enderror
	@endif
</div>

