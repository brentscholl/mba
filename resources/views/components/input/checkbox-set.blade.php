@props([
    'compact' => false,
    'label' => '',
    'inline' => false,
    'optional' => false,
    'align' => 'justify-start'
])
<div class="{{ $attributes->get('class') }}" {{ $attributes->whereStartsWith('x') }}>
    @if($label)
    <div class="flex justify-between">
        <label for="{{ $attributes->whereStartsWith('wire:model')->first() }}">{!! $label !!}</label>
        @if($optional)
            <span class="text-xs text-gray-500" id="email-optional">Optional</span>
        @endif
    </div>
    @endif
    <fieldset class="
    mt-2
    @if($inline)
        {{ $align }} flex gap-6 flex-wrap
    @else
    {{ $align }} {{ $compact ? 'space-y-2' : 'space-y-5' }}
    @endif
    ">
        {{ $slot }}
    </fieldset>
</div>
