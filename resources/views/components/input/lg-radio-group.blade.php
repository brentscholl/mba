@props([
    'index' => 0,
    'label' => ''
])
<fieldset {{ $attributes }} x-data="window.Components.radioGroup({ initialCheckedIndex: {{ $index }} })" x-init="init()">
    @if($label)
        <legend class="text-sm font-medium text-gray-900">
            {{ $label }}
        </legend>
    @endif
    <div class="mt-2 grid grid-cols-1 gap-y-6 sm:grid-cols-3 sm:gap-x-4">
        {{ $slot }}
    </div>
</fieldset>
