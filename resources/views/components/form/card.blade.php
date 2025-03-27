@props([
	'title' => '',
	'description' => '',
	'actions' => '',
	'twoColumn' => false,
	'submit' => '',
])
<div class="shadow rounded-md">
    @if($submit)
        <form wire:submit="{{ $submit }}">
    @endif
        <div class="bg-white py-6 px-4 sm:p-6
            @if($actions) rounded-t-md @else rounded-md @endif
            @if($twoColumn) md:grid md:grid-cols-3 md:gap-6 @else space-y-6 @endif "
        >
            <div class=" @if($twoColumn) md:col-span-1 @endif ">
                <h3 class="text-lg leading-6 font-medium text-gray-900">{{ $title }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
            </div>

            <div class=" @if($twoColumn) mt-0 md:col-span-2 @endif grid grid-cols-3 gap-6">
                    {{ $slot }}
            </div>
        </div>
        @if($actions)
            <div class="px-4 py-3 bg-secondary-50 rounded-b-md text-right sm:px-6 border-t border-secondary-100">
                {{ $actions }}
            </div>
        @endif
    @if($submit)
        </form>
    @endif
</div>
