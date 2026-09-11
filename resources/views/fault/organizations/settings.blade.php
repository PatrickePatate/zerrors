@extends('layouts.app')

@section('title', 'Settings · '.$organization->name)

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">Organization settings</h1>

    @if(in_array($myRole, ['owner', 'admin']))
        <livewire:organization-general-settings :organization="$organization" />
        <livewire:organization-ai-settings :organization="$organization" />

        <x-card class="mb-6">
            <h2 class="mb-1 text-sm font-medium text-gray-700">GitHub</h2>
            <p class="mb-3 text-sm text-gray-500">Connects a GitHub App installation, used for creating linked issues and recording releases from pushes.</p>

            @if($organization->hasGithubConnected())
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-700">
                        Connected to <strong>{{ $organization->github_account_login }}</strong> ({{ $organization->github_account_type }})
                    </p>
                    <form method="POST" action="{{ route('integrations.github.disconnect', $organization) }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="secondary">Disconnect</x-button>
                    </form>
                </div>
            @else
                <a href="{{ route('integrations.github.redirect', $organization) }}">
                    <x-button type="button">Connect GitHub</x-button>
                </a>
            @endif
        </x-card>

        <x-card class="mb-6">
            <h2 class="mb-1 text-sm font-medium text-gray-700">Slack</h2>
            <p class="mb-3 text-sm text-gray-500">Connects a Slack App installation, used for sending issue alerts to a channel.</p>

            @if($organization->hasSlackConnected())
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-700">
                        Connected to <strong>{{ $organization->slack_team_name }}</strong>
                    </p>
                    <form method="POST" action="{{ route('integrations.slack.disconnect', $organization) }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="secondary">Disconnect</x-button>
                    </form>
                </div>
            @else
                <a href="{{ route('integrations.slack.redirect', $organization) }}">
                    <x-button type="button">Connect Slack</x-button>
                </a>
            @endif
        </x-card>
    @endif

    <x-card class="mb-6">
        <h2 class="mb-1 text-sm font-medium text-gray-700">Leave organization</h2>
        <p class="mb-3 text-sm text-gray-500">You'll lose access to its projects and issues until someone invites you back.</p>
        <form method="POST" action="{{ route('organizations.leave', $organization) }}" x-data
              @submit="if (! confirm('Leave {{ $organization->name }}?')) $event.preventDefault()">
            @csrf
            <x-button type="submit" variant="secondary">Leave organization</x-button>
        </form>
        @error('leave')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </x-card>

    @if($myRole === 'owner')
        <x-card variant="danger">
            <h2 class="mb-1 flex items-center gap-1.5 text-sm font-medium text-red-700">
                <x-lucide-triangle-alert class="h-4 w-4" />
                Danger zone
            </h2>
            <p class="mb-3 text-sm text-gray-500">
                Deletes <strong>{{ $organization->name }}</strong> and everything in it — projects, issues, events, invites — for every member. This cannot be undone.
            </p>

            @if(auth()->user()->organizations()->count() <= 1)
                <p class="text-sm text-gray-500">You must belong to another organization before you can delete your last one.</p>
            @else
                <form method="POST" action="{{ route('organizations.settings.destroy', $organization) }}"
                      x-data="{ confirmation: '' }" class="flex items-end gap-3">
                    @csrf
                    @method('DELETE')
                    <div class="max-w-xs flex-1">
                        <label for="confirm_name" class="mb-1 block text-sm font-medium text-gray-700">
                            Type <span class="font-semibold">{{ $organization->name }}</span> to confirm
                        </label>
                        <input type="text" id="confirm_name" name="confirm_name" x-model="confirmation" autocomplete="off"
                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                        @error('confirm_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <x-button type="submit" variant="danger" x-bind:disabled="confirmation !== '{{ $organization->name }}'">
                        Delete organization
                    </x-button>
                </form>
            @endif
        </x-card>
    @endif
@endsection
