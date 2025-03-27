@props([
    'enabled' => false,
    'entangle',
    'tooltipEnabled',
    'tooltipDisabled',
])
<button type="button"
    @if($enabled)
        tooltip="{{ $tooltipEnabled }}"
    @else
        tooltip="{{ $tooltipDisabled }}"
    @endif
    {{ $attributes->whereStartsWith('wire:click') }}
    class="mr-3 relative inline-flex h-6 w-11 grow-0 cursor-pointer rounded-full bg-secondary-600 border-2 border-transparent transition-colors duration-200 ease-in-out
            focus:outline-none focus:ring-2 focus:ring-secondary-500 focus:ring-offset-2
            "
    x-data="{ on: @entangle($entangle).live }" role="switch" aria-checked="true" :aria-checked="on.toString()" @click="on = !on"
    x-state:on="Enabled" x-state:off="Not Enabled" :class="{ 'bg-secondary-600': on, 'bg-gray-200 ': !(on) }">
    <span
        class="pointer-events-none relative inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-5 "
        x-state:on="Enabled" x-state:off="Not Enabled" :class="{ 'translate-x-5': on, 'translate-x-0': !(on) }">
      <span
          class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity opacity-0 ease-out duration-100"
          aria-hidden="true" x-state:on="Enabled" x-state:off="Not Enabled"
          :class="{ 'opacity-0 ease-out duration-100': on, 'opacity-100 ease-in duration-200': !(on) }">
            <svg class="h-3 w-3 text-gray-400 " fill="none" viewBox="0 0 12 12">
                <path d="M4 8l2-2m0 0l2-2M6 6L4 4m2 2l2 2" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"></path>
            </svg>
      </span>
      <span
          class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity opacity-100 ease-in duration-200"
          aria-hidden="true" x-state:on="Enabled" x-state:off="Not Enabled"
          :class="{ 'opacity-100 ease-in duration-200': on, 'opacity-0 ease-out duration-100': !(on) }">
            <svg class="h-3 w-3 text-secondary-600" fill="currentColor" viewBox="0 0 12 12">
                <path
                    d="M3.707 5.293a1 1 0 00-1.414 1.414l1.414-1.414zM5 8l-.707.707a1 1 0 001.414 0L5 8zm4.707-3.293a1 1 0 00-1.414-1.414l1.414 1.414zm-7.414 2l2 2 1.414-1.414-2-2-1.414 1.414zm3.414 2l4-4-1.414-1.414-4 4 1.414 1.414z"></path>
            </svg>
      </span>
    </span>
</button>
