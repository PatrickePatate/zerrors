<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Status')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/bug.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div class="mx-auto max-w-3xl px-4 py-8">
        @yield('content')

        <p class="mt-10 text-center text-xs text-gray-400">
            Powered by <a href="/" class="hover:underline">Zerrors</a>
        </p>
    </div>
</body>
</html>
