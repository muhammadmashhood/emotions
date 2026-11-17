<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'eMotion Evaluations') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('images/emotion-mark.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased" x-data="{ sidebarOpen: false }">
        <div class="min-h-screen bg-paper lg:flex">
            <livewire:layout.navigation />

            <div class="flex-1 min-w-0 flex flex-col">
                <header class="lg:hidden sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-line px-4 h-14 flex items-center justify-between no-print">
                    <button type="button" @click="sidebarOpen = true" class="p-2 -ml-2 rounded-lg text-ink-soft hover:bg-paper" aria-label="Open menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <x-application-logo size="h-7" />
                    <span class="w-6"></span>
                </header>

                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
