<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F6F5F3; color: #2B1F26; font-family: Poppins, system-ui, sans-serif; padding: 16px; }
        .card { background: #fff; border: 1px solid #ECE9E7; border-radius: 16px; padding: 32px; max-width: 28rem; width: 100%; text-align: center; }
        .code { font-size: 11px; letter-spacing: .12em; color: #7A7277; }
        .mark { display: inline-flex; align-items: center; gap: 9px; margin-bottom: 20px; font-size: 17px; font-weight: 300; letter-spacing: .09em; }
        .mark span { width: 32px; height: 32px; border-radius: 9px; background: #2B1F26; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 21px; font-weight: 400; letter-spacing: 0; padding-bottom: 3px; box-sizing: border-box; }
        h1 { font-size: 22px; margin: 8px 0; }
        p { color: #6E666B; margin: 0 0 20px; }
        a { display: inline-block; background: #2B1F26; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; }
        a:focus-visible { outline: 2px solid #5B56B9; outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark"><span aria-hidden="true">e</span>MOTION</div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Back to the dashboard</a>
    </main>
</body>
</html>
