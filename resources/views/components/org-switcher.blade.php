@props(['organization'])

@php($memberOrgs = auth()->user()->organizations()->orderBy('name')->get())

<div class="border-b border-gray-200 p-3">
    <x-dropdown class="max-h-64 overflow-y-auto">
        <x-slot:trigger>
            <button type="button"
                    class="flex w-full items-center justify-between rounded-lg border border-gray-200 px-2.5 py-2 text-sm hover:bg-gray-50">
                <span class="flex items-center gap-2 truncate">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-indigo-100 text-[11px] font-semibold text-indigo-700">
                        {{ Str::upper(Str::substr($organization->name, 0, 1)) }}
                    </span>
                    <span class="truncate font-medium text-gray-700">{{ $organization->name }}</span>
                </span>
                <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-gray-400" />
            </button>
        </x-slot:trigger>

        @foreach($memberOrgs as $org)
            <x-dropdown-link href="{{ route('organizations.projects.index', $org) }}"
                              class="justify-between {{ $org->id === $organization->id ? 'font-medium text-gray-900' : '' }}">
                {{ $org->name }}
                @if($org->id === $organization->id)
                    <x-lucide-check class="h-3.5 w-3.5 text-indigo-600" />
                @endif
            </x-dropdown-link>
        @endforeach
        <div class="my-1 border-t border-gray-100"></div>
        <x-dropdown-link href="{{ route('organizations.index') }}" class="text-gray-500">
            Manage organizations
        </x-dropdown-link>
    </x-dropdown>
</div>
