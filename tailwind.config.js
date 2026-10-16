import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Public Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: '#123A4A',
                'ink-soft': '#4E6875',
                paper: '#F7F9FA',
                line: '#DCE3E7',
                rag: {
                    green: '#2F855A',
                    amber: '#B7791F',
                    red: '#B83A33',
                    neutral: '#A9B6BD',
                },
            },
        },
    },

    plugins: [forms],
};
