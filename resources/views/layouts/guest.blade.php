<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'eMotion Evaluations') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-em-ink">
        {{-- Sign-in screens (eMotion Dashboard design): brand story on the left, the form on the right. --}}
        <div class="auth-dark min-h-screen flex flex-wrap bg-em-ink text-white">
            <div class="flex-[1_1_420px] px-6 sm:px-[52px] py-8 sm:py-11 flex flex-col gap-10 min-w-0">
                <x-application-logo dark />
                <div class="hidden md:flex flex-1 min-h-[300px] -mx-6 sm:-mx-[52px]" aria-hidden="true">
                    <canvas data-login-viz class="block w-full h-full min-h-[300px]"></canvas>
                </div>
                <div class="hidden md:flex max-w-[520px] flex-col gap-[18px]">
                    <div class="text-[11px] tracking-[0.12em] uppercase text-[#C5BFC2]">eMotion MS · Mystery shopping</div>
                    <p class="text-[40px] font-light leading-[1.15] tracking-[-0.02em]">Every visit measured. <span class="text-[#A8A4F7]">Every touchpoint</span> visible.</p>
                    <p class="text-sm text-[#D9D3D9] leading-relaxed max-w-[420px]">Live results from every mystery-shopping visit, scored and published to your team within three business days.</p>
                </div>
                <div class="hidden md:block text-[11.5px] text-[#A8A3A8] leading-relaxed">eMotion Healthtech LLC-FZ · Meydan Grandstand, 6th floor, Nad Al Sheba, Dubai, U.A.E.</div>
            </div>
            <div class="flex-[1_1_420px] flex items-center justify-center px-6 sm:px-8 py-10 sm:py-12">
                <div class="w-full max-w-[360px] flex flex-col gap-[22px]">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
