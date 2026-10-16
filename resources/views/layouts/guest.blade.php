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
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-paper px-4">
            <div class="flex items-center gap-3">
                <x-application-logo class="w-10 h-10" />
                <span class="text-lg font-semibold tracking-tight">{{ config('app.name', 'eMotion Evaluations') }}</span>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-6 panel">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
