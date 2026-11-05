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
                sans: ['var(--font-sans)', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Tokens are CSS variables (see resources/css/app.css) so the admin
                // area can switch palette with the `.theme-admin` class.
                ink: 'rgb(var(--c-ink) / <alpha-value>)',
                'ink-soft': 'rgb(var(--c-ink-soft) / <alpha-value>)',
                paper: 'rgb(var(--c-paper) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                accent: {
                    DEFAULT: 'rgb(var(--c-accent) / <alpha-value>)',
                    soft: 'rgb(var(--c-accent-soft) / <alpha-value>)',
                    deep: 'rgb(var(--c-accent-deep) / <alpha-value>)',
                },
                night: {
                    DEFAULT: '#0E2A38',
                    deep: '#08202B',
                },
                rag: {
                    green: 'rgb(var(--c-rag-green) / <alpha-value>)',
                    amber: 'rgb(var(--c-rag-amber) / <alpha-value>)',
                    red: 'rgb(var(--c-rag-red) / <alpha-value>)',
                    neutral: 'rgb(var(--c-rag-neutral) / <alpha-value>)',
                },
                // Admin design palette (eMotion Admin design).
                adm: {
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
