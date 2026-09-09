@php($user = auth()->user())

<div class="border-t border-gray-200 p-3">
    <x-dropdown up>
        <x-slot:trigger>
            <button type="button" class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-gray-50">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold text-white">
                    {{ Str::upper(Str::substr($user->name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-gray-800">{{ $user->name }}</span>
                </span>
                <x-lucide-chevrons-up-down class="h-4 w-4 shrink-0 text-gray-400" />
            </button>
        </x-slot:trigger>

        <x-dropdown-link href="{{ route('organizations.index') }}">Organizations</x-dropdown-link>
        <x-dropdown-link href="{{ route('security.edit') }}">Security</x-dropdown-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-dropdown-link tag="button" type="submit">Log out</x-dropdown-link>
        </form>
    </x-dropdown>
</div>
