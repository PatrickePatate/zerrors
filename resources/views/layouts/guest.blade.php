<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Zerrors')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        window.livewireScriptConfig = {
            csrf: '{{ csrf_token() }}',
            uri: '{{ route('default-livewire.update') }}',
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-50 font-sans text-gray-900 antialiased">
    <div class="w-full max-w-sm px-4 py-12">
        <a href="/" class="mb-8 flex items-center justify-center gap-2 text-lg font-semibold text-gray-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-900 text-sm font-bold text-white">Z</span>
            Zerrors
        </a>

        @if(session('status'))
            <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
