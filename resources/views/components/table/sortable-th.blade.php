@props([
    'sortFieldValue',
    'sortField',
    'sortAsc'
])
<th scope="col" class="{{ $attributes->get('class') }}">
    <button wire:click="sortBy('{{ $sortFieldValue }}')"
        class="group flex grow-0 items-center justify-start space-x-2 text-xs font-medium uppercase {{ $sortField === $sortFieldValue ? 'text-gray-900 underline' : 'text-gray-500' }} hover:text-gray-900 ">
        <span class="">{{ $slot }}</span>
        @if($sortField === $sortFieldValue)
            @if($sortAsc)
                <x-svg.sort-desc class="h-3 w-3 grow-0"/>
            @else
                <x-svg.sort-asc class="h-3 w-3 grow-0"/>
            @endif
        @else
            <x-svg.sort class="opacity-60 group-hover:opacity-100 h-3 w-3 grow-0"/>
        @endif
    </button>
</th>
