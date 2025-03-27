@props([
    'type' => "text",
    'label' => "",
    'required' => false,
    'placeholder' => "",
    'lead' => "",
    'tail' => "",
    'subtext' => '',
    'optional' => false,
])
<div class="inline-block {{ $attributes->get('class') }} py-0.5">
	<div class="form-inline-input-container">
		@if($lead)
			<span class="form-input-container__lead">
              {{ $lead }}
            </span>
		@endif
		<input
			{{ $attributes->whereStartsWith('min') }}
			{{ $attributes->whereStartsWith('max') }}
			{{ $attributes->whereStartsWith('maxlength') }}
			{{ $attributes->whereStartsWith('wire:model') }}
			type="{{ $type }}"
			placeholder="{{ $placeholder }}"
			id="{{ $attributes->whereStartsWith('wire:model')->first() }}"
			@if($required) required @endif
			onkeydown="return event.key !== 'Enter';"

			@error($attributes->whereStartsWith('wire:model')->first())
				class="text-center form-input-container__input form-input-container__input--has-error @if($lead) form-input-container__input--has-lead @endif @if($tail) form-input-container__input--has-tail @endif"
				aria-invalid="true"
				aria-describedby="{{ $attributes->whereStartsWith('wire:model')->first() }}-error"
			@else
				class="text-center form-input-container__input @if($lead) form-input-container__input--has-lead @endif @if($tail) form-input-container__input--has-tail @endif"
			@endif
		>
		@if($tail)
			<span class="form-input-container__tail">
			{{ $tail }}
		</span>
		@endif
		@error($attributes->whereStartsWith('wire:model')->first())
		<div wire:key="error_svg_{{ $attributes->whereStartsWith('wire:model')->first() }}"
			class="form-input-container__input__icon--has-error"
		>
			<x-svg.error/>
		</div>
		@enderror
	</div>
	@if($subtext)
		<p class="mt-1 text-xs text-gray-500">{!! $subtext !!}</p>
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

