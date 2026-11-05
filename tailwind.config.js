import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-sans)', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Semantic tokens as CSS variables (resources/css/app.css): ink, paper,
                // line and accent, plus the score-band bar colours (Good / Watch / Poor).
                ink: 'rgb(var(--c-ink) / <alpha-value>)',
                'ink-soft': 'rgb(var(--c-ink-soft) / <alpha-value>)',
                paper: 'rgb(var(--c-paper) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                accent: {
                    DEFAULT: 'rgb(var(--c-accent) / <alpha-value>)',
                    soft: 'rgb(var(--c-accent-soft) / <alpha-value>)',
                    deep: 'rgb(var(--c-accent-deep) / <alpha-value>)',
                },
                rag: {
                    green: 'rgb(var(--c-rag-green) / <alpha-value>)',
                    amber: 'rgb(var(--c-rag-amber) / <alpha-value>)',
                    red: 'rgb(var(--c-rag-red) / <alpha-value>)',
                    neutral: 'rgb(var(--c-rag-neutral) / <alpha-value>)',
                },
                // eMotion design palette (shared by the admin and hospital screens).
                em: {
                    bg: '#F6F5F3',
                    side: '#FBFAF9',
                    ink: '#2B1F26',
                    soft: '#6E666B',
                    mute: '#7A7277',
                    line: '#ECE9E7',
                    field: '#E2DDDF',
                    hover: '#C5BFC2',
                    accent: '#5B56B9',
                    'accent-soft': '#F1F0FE',
                    'ok-bg': '#F7F5D3',
                    ok: '#5F5A00',
                    'warn-bg': '#FCEFD9',
                    warn: '#8A5A0E',
                    'bad-bg': '#FDE7E5',
                    bad: '#B73228',
                    'muted-bg': '#F3F1F0',
                },
            },
        },
    },

    plugins: [forms],
};
