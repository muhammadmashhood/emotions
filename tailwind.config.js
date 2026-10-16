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
                accent: {
                    DEFAULT: '#2457F5',
                    soft: '#EAF0FF',
                    deep: '#1A3FBF',
                },
                night: {
                    DEFAULT: '#0E2A38',
                    deep: '#08202B',
                },
                rag: {
                    green: '#2F855A',
                    amber: '#B7791F',
                    red: '#B83A33',
                    neutral: '#A9B6BD',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(18, 58, 74, 0.05), 0 12px 32px -16px rgba(18, 58, 74, 0.18)',
                glow: '0 0 0 1px rgba(36, 87, 245, 0.25), 0 10px 30px -10px rgba(36, 87, 245, 0.45)',
            },
            borderRadius: {
                xl2: '1.25rem',
            },
        },
    },

    plugins: [forms],
};
