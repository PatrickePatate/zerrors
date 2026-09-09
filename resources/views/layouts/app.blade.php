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
<body class="h-screen overflow-hidden bg-gray-50 font-sans text-gray-900 antialiased">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 z-30 bg-gray-900/30 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0"
        >
            <div class="flex h-14 items-center gap-2 border-b border-gray-200 px-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold text-gray-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-900 text-sm font-bold text-white">Z</span>
                    <span>Zerrors</span>
                </a>
            </div>

            @isset($organization)
                <x-org-switcher :organization="$organization" />
            @endisset

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                <p class="px-2 pb-1 text-xs font-medium tracking-wide text-gray-400 uppercase">Workspace</p>
                @isset($organization)
                    <x-nav-link :href="route('organizations.projects.index', $organization)" :active="request()->routeIs('organizations.projects.*')">
                        <x-lucide-home class="h-4 w-4" />
                        Projects
                    </x-nav-link>
                    <x-nav-link :href="route('organizations.members.index', $organization)" :active="request()->routeIs('organizations.members.*')">
                        <x-lucide-users class="h-4 w-4" />
                        Members
                    </x-nav-link>
                    <x-nav-link :href="route('organizations.settings.edit', $organization)" :active="request()->routeIs('organizations.settings.*')">
                        <x-lucide-settings class="h-4 w-4" />
                        Settings
                    </x-nav-link>
                    @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                        <x-nav-link :href="route('organizations.audit.index', $organization)" :active="request()->routeIs('organizations.audit.*')">
                            <x-lucide-scroll-text class="h-4 w-4" />
                            Audit log
                        </x-nav-link>
                    @endif
                @else
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <x-lucide-home class="h-4 w-4" />
                        Dashboard
                    </x-nav-link>
                @endisset
            </nav>

            <div class="border-t border-gray-200 px-3 py-3">
                <a href="/horizon" target="_blank"
                   class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <x-lucide-zap class="h-4 w-4" />
                    Horizon queues
                </a>
                <a href="https://docs.sentry.io/platforms/php/guides/laravel/" target="_blank"
                   class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <x-lucide-book-open class="h-4 w-4" />
                    SDK docs
                </a>
            </div>

            @auth
                <x-user-menu />
            @endauth
        </aside>

        {{-- Main --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4 lg:hidden">
                <button @click="sidebarOpen = true" class="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100">
                    <x-lucide-menu class="h-5 w-5" />
                </button>
                <span class="font-semibold text-gray-900">Zerrors</span>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                <div class="mx-auto max-w-7xl">
                    @if(session('status'))
                        <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                            {{ session('status') }}
                        </div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
