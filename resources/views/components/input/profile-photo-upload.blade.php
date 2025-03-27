@props([
	'label' => '',
])
<div x-data="{photoName: null, photoPreview: null}" class="col-span-2">
    <!-- Profile Photo File Input -->
    <input type="file" class="hidden"
        {{ $attributes->whereStartsWith('wire:model') }}
        x-ref="photo"
        x-on:change="
            photoName = $refs.photo.files[0].name;
            const reader = new FileReader();
            reader.onload = (e) => {
                photoPreview = e.target.result;
            };
            reader.readAsDataURL($refs.photo.files[0]);
        "
    />

    <label for="{{ $attributes->whereStartsWith('wire:model')->first() }}">{!! $label !!}</label>

    <div class="mt-1 flex items-center space-x-5">
        <span class="inline-block h-20 w-20 rounded-full overflow-hidden bg-gray-100">
            <!-- Current Profile Photo -->
            <div x-show="! photoPreview">
                <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->fullname }}" class="rounded-full h-20 w-20 object-cover dark-hide">
                <img src="{{ $this->user->dark_profile_photo_url }}" alt="{{ $this->user->fullname }}" class="rounded-full h-20 w-20 object-cover border border-gray-600 dark-show">
            </div>

            <!-- New Profile Photo Preview -->
            <div x-show="photoPreview">
                <span class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center object-cover"
                    x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                </span>
            </div>
        </span>

        <button type="button"
            x-on:click.prevent="$refs.photo.click()"
            class="bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700
            hover:bg-gray-50
            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-secondary-500"
        >
            {{ __('Select A New Photo') }}
        </button>

        @if ($this->user->profile_photo_path)
            <button type="button"
                wire:click="deleteProfilePhoto"
                class="bg-white py-2 px-3 border border-red-300 rounded-md shadow-sm text-sm leading-4 font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
                {{ __('Remove Photo') }}
            </button>
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
</div>
