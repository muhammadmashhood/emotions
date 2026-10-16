<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F7F9FA; color: #123A4A; font-family: "Public Sans", system-ui, sans-serif; padding: 16px; }
        .card { background: #fff; border: 1px solid #DCE3E7; border-radius: 12px; padding: 32px; max-width: 28rem; width: 100%; text-align: center; }
        .code { font-size: 12px; letter-spacing: .2em; color: #4E6875; }
        h1 { font-size: 22px; margin: 8px 0; }
        p { color: #4E6875; margin: 0 0 20px; }
        a { display: inline-block; background: #123A4A; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 8px; font-size: 14px; }
        a:focus-visible { outline: 2px solid #123A4A; outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="card">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="{{ url('/') }}">Back to the dashboard</a>
    </main>
</body>
</html>
