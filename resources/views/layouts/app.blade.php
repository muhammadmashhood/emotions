<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'eMotion Evaluations') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen bg-paper lg:flex">
            <livewire:layout.navigation />

            <div class="flex-1 min-w-0 flex flex-col">
                <header class="lg:hidden sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-line px-4 h-14 flex items-center justify-between no-print">
                    <button type="button" @click="sidebarOpen = true" class="p-2 -ml-2 rounded-lg text-ink-soft hover:bg-paper" aria-label="Open menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <span class="font-semibold text-ink">{{ config('app.name') }}</span>
                    <span class="w-6"></span>
                </header>

                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
