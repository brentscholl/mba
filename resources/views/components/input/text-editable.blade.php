@props([
    'type' => "text",
    'label' => "",
    'required' => false,
    'placeholder' => "",
    'required' => false,
    'autofocus' => false,
    'submit' => 'save'
])
<div
        wire:key="data_{{ $attributes->whereStartsWith('wire:model')->first() }}"
        x-data="{
        isEditing: false,
        focus: function() {
            const textInput = this.$refs.textInput;
            textInput.focus();
            textInput.select();
        }
    }"
        x-init="@this.on('notify-saved', () => { isEditing = false })"
        class="py-2 items-center sm:grid sm:grid-cols-2 sm:gap-4 {{ $attributes->get('class') }}"
>
    <!-- Not Editing -->
    <dd x-show="!isEditing"
        x-transition:enter="transform ease-out duration-300 transition"
        x-transition:enter-start="opacity-0 translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        class="flex space-x-4 text-sm items-center leading-5 text-gray-900 sm:mt-0 sm:col-span-2"
    >
        <span class="grow py-2">{{ $slot }}</span>
        @if(hasUpdatePermission())
        <span class="grow-0">
            <button
                    tooltip="Edit"
                    type="button"
                    @click="isEditing = true; $nextTick(() => focus())"
                    class="font-medium text-gray-400 rounded-full bg-secondary-50 flex items-center justify-center h-6 w-6
                    hover:bg-secondary-100 hover:text-gray-500
                   "
            >
                <x-svg.edit class="h-4 w-4"/>
            </button>
        </span>
        @endif
    </dd>

    <!-- Editing -->
    @if(hasUpdatePermission())
    <dd x-show="isEditing" x-cloak
        x-transition:enter="ease-out duration-300 transition"
        x-transition:enter-start="opacity-0 -translate-x-2"
        x-transition:enter-end="opacity-100 translate-x-0"
        class="sm:mt-0 sm:col-span-2"
    >
        <form wire:submit="{{ $submit }}"
              class="flex justify-between space-x-4 items-center text-sm leading-5 text-gray-900"
        >

            <span class="flex grow-1 space-x-4">
                <span class="grow">
                    <div class="max-w-lg rounded-md shadow-sm sm:max-w-xs">
                        @if($label)
                            <label for="{{ $attributes->whereStartsWith('wire:model')->first() }}">{{ $label }}</label>
                        @endif
                        <div class="form-input-container--simple">
                            <input
                                    {{ $attributes->whereStartsWith('wire:model') }}
                                    type="{{ $type }}"
                                    placeholder="{{ $placeholder }}"
                                    id="{{ $attributes->whereStartsWith('wire:model')->first() }}"
                                    @if($required) required @endif
                                    onkeydown="return event.key !== 'Enter';"

                                    @error($attributes->whereStartsWith('wire:model')->first())
                                    class="form-input-container__input--simple form-input-container__input--has-error"
                                    aria-invalid="true"
                                    aria-describedby="{{ $attributes->whereStartsWith('wire:model')->first() }}-error"
                                    @else
                                        class="form-input-container__input--simple"
                                    @endif
                            >
                            @error($attributes->whereStartsWith('wire:model')->first())
                                <div wire:key="error_svg_{{ $attributes->whereStartsWith('wire:model')->first() }}"
                                     class="form-input-container__input__icon--has-error"
                                >
                                    <x-svg.error/>
                                </div>
                            @enderror
                        </div>
                        @error($attributes->whereStartsWith('wire:model')->first())
                            <p wire:key="error_{{ $attributes->whereStartsWith('wire:model')->first() }}"
                               class="form-input-container__input__error-message"
                               id="error-{{ $attributes->whereStartsWith('wire:model')->first() }}"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </span>
            </span>

            <span class="grow-0 flex items-start space-x-4">
                <button
                        tooltip="Cancel"
                        type="button"
                        wire:click="cancel"
                        @click="isEditing = false"
                        class="font-medium text-red-600 h-6 w-6 rounded-full bg-red-100 flex items-center justify-center
                        hover:text-white hover:bg-red-500
                       "
                >
                  <x-svg.x class="h-4 w-4"/>
                </button>
                <span class="text-gray-300" aria-hidden="true">|</span>
                <button
                        tooltip="Save"
                        type="submit"
                        class="font-medium text-emerald-400 h-6 w-6 rounded-full bg-emerald-100 flex items-center justify-center
                        hover:text-white hover:bg-emerald-500
                       "
                >
                  <x-svg.check class="h-4 w-4"/>
                </button>
            </span>
        </form>
    </dd>
    @endif
</div>