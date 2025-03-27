import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/sass/**/*.scss',
        './config/livewire-flash.php'
    ],
    theme: {
        extend: {
            fontSize: {
                'xxs': '0.55rem',
            },
            fontFamily: {
                sans: ['Hanken Grotesk', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                'xl': '1rem',
            },
            colors: {
                primary: {
                    DEFAULT: '#9e8da8',
                    50: '#faf9fa',
                    100: '#f4f2f5',
                    200: '#e7e3eb',
                    300: '#d5cdda',
                    400: '#bcafc3',
                    500: '#9e8da8',
                    600: '#806f8a',
                    700: '#66576e',
                    800: '#574a5e',
                    900: '#4a404f',
                    950: '#2b232f',
                },
                secondary: {
                    50: '#f5f9f4',
                    100: '#e9f2e6',
                    200: '#d3e5cd',
                    300: '#b0cfa6',
                    400: '#85b177',
                    500: '#639354',
                    600: '#4f7942',
                    700: '#405f36',
                    800: '#354d2e',
                    900: '#2c4027',
                    950: '#152112',
                },
                gray: {
                    750: '#242e3c',
                    850: '#141b2a',
                    950: '#0a0e16',
                },
            },
        },
    },
    plugins: [forms, typography],
};
