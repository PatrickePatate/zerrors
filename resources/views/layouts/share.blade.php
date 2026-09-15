<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Zerrors')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <script>
        window.livewireScriptConfig = {
            csrf: '{{ csrf_token() }}',
            uri: '{{ route('default-livewire.update') }}',
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="mx-auto max-w-4xl px-4 py-8">
        <a href="/" class="mb-6 flex items-center gap-2 text-lg font-semibold text-gray-900">
            <img src="{{ asset('images/logo.png') }}" alt="Zerrors" class="h-7 w-auto">
        </a>

        @if(session('status'))
            <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-4 rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs text-gray-500">
            <x-lucide-link class="mr-1 -ms-0.5 inline h-3.5 w-3.5" /> You're viewing this issue via a shared, read-only link.
        </div>

        @yield('content')
    </div>
</body>
</html>
