<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

        @stack('styles')

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            <nav class="bg-white border-b border-gray-200 px-6">
                <ul class="flex space-x-4">
                    <li>
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Upload a File') }}
                        </x-nav-link>
                    </li>
                    <li>
                        <x-nav-link :href="route('files.index')" :active="request()->routeIs('files.index')">
                            {{ __('All Files') }}
                        </x-nav-link>
                    </li>
                </ul>
            </nav>
            <!-- Page Content -->
            <main>
                <div class="w-full px-6">
                    @yield('content')
                </div>
            </main>
        </div>
        @livewireScripts
    </body>
</html>
