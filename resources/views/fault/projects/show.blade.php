@extends('layouts.app')

@section('title', $project->name.' · Zerrors')

@section('content')
    <a href="{{ route('organizations.projects.index', $organization) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> Projects
    </a>

    <x-card class="mb-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-gray-900">
                    {{ $project->name }}
                    <x-badge class="ml-2">{{ \App\Models\FaultProject::PLATFORMS[$project->platform] ?? $project->platform }}</x-badge>
                </h1>
                <p class="mt-1 text-sm text-gray-500">DSN: <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $project->dsn() }}</code></p>
            </div>
            @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                <div class="flex items-center gap-2">
                    @php
                        $notificationErrors = $errors->has('slack_webhook_url') || $errors->has('telegram_bot_token') || $errors->has('telegram_chat_id') || $errors->has('notify_email');
                    @endphp
                    <x-modal
                        :open-on-error="$errors->has('platform') || $errors->has('github_repo') || $errors->has('github_token') || $notificationErrors"
                        max-width="lg"
                    >
                        <x-slot:trigger>
                            <x-button type="button" variant="secondary">Edit project</x-button>
                        </x-slot:trigger>

                        <div x-data="{ tab: @js($notificationErrors ? 'notifications' : 'details') }">
                            <div class="mb-4 flex gap-1 border-b border-gray-200">
                                <button type="button" @click="tab = 'details'"
                                        :class="tab === 'details' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="border-b-2 px-3 pb-2 text-sm font-medium">Details</button>
                                <button type="button" @click="tab = 'notifications'"
                                        :class="tab === 'notifications' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="border-b-2 px-3 pb-2 text-sm font-medium">Notifications</button>
                            </div>

                            <div x-show="tab === 'details'">
                                <form method="POST" action="{{ route('organizations.projects.settings.update', [$organization, $project]) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label for="platform-edit" class="mb-1 block text-sm font-medium text-gray-700">Type</label>
                                        <select id="platform-edit" name="platform" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                                            @foreach(\App\Models\FaultProject::PLATFORMS as $value => $label)
                                                <option value="{{ $value }}" @selected($project->platform === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <x-input label="GitHub repo (owner/repo)" name="github_repo" value="{{ $project->github_repo }}" placeholder="acme/api" :error="$errors->first('github_repo')" />
                                        <p class="mt-1 text-xs text-gray-400">Lets you create a linked GitHub issue straight from an issue page.</p>
                                    </div>
                                    <div>
                                        <x-input label="GitHub token" name="github_token" type="password" placeholder="{{ $project->github_token ? '••••••••' : 'ghp_…' }}" :error="$errors->first('github_token')" />
                                        <p class="mt-1 text-xs text-gray-400">Personal access token with "repo" scope. Leave blank to keep the current one.</p>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                        <x-button type="submit">Save</x-button>
                                    </div>
                                </form>
                            </div>

                            <div x-show="tab === 'notifications'" x-cloak>
                                <form method="POST" action="{{ route('organizations.projects.notifications.update', [$organization, $project]) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <x-input label="Slack webhook URL" name="slack_webhook_url" value="{{ $project->slack_webhook_url }}"
                                                  placeholder="https://hooks.slack.com/services/…" :error="$errors->first('slack_webhook_url')" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-input label="Telegram bot token" name="telegram_bot_token" type="password"
                                                  placeholder="{{ $project->telegram_bot_token ? '••••••••' : '123456:ABC-…' }}" :error="$errors->first('telegram_bot_token')" />
                                        <x-input label="Telegram chat ID" name="telegram_chat_id" value="{{ $project->telegram_chat_id }}"
                                                  placeholder="-100123456789" :error="$errors->first('telegram_chat_id')" />
                                    </div>
                                    <p class="-mt-2 text-xs text-gray-400">Message @BotFather to create a bot, then add it to a chat/channel to find the chat ID.</p>
                                    <div>
                                        <x-input label="Alert email" type="email" name="notify_email" value="{{ $project->notify_email }}"
                                                  placeholder="alerts@example.com" :error="$errors->first('notify_email')" />
                                        <p class="mt-1 text-xs text-gray-400">Sent in addition to organization members, if email alerts are enabled in organization settings.</p>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                        <x-button type="submit">Save</x-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </x-modal>

                    <form method="POST" action="{{ route('organizations.projects.rotateKey', [$organization, $project]) }}"
                          x-data @submit="if (! confirm('Rotate the DSN key? Every SDK using the current key will stop working until updated.')) $event.preventDefault()">
                        @csrf
                        <x-button type="submit" variant="secondary">Rotate DSN key</x-button>
                    </form>
                </div>
            @endif
        </div>
    </x-card>

    <x-card class="mb-6" :padding="false">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-medium text-gray-700">Releases</h2>
            @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                <form method="POST" action="{{ route('organizations.projects.releases.store', [$organization, $project]) }}" class="flex items-end gap-2">
                    @csrf
                    <input type="text" name="version" placeholder="v1.4.2" required
                           class="w-32 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                    <input type="text" name="notes" placeholder="Notes (optional)"
                           class="w-48 rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                    <x-button type="submit" variant="secondary" class="text-xs">Mark deploy</x-button>
                </form>
            @endif
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($releases as $release)
                <div class="flex items-center justify-between px-5 py-2 text-sm">
                    <div>
                        <span class="font-mono font-medium text-gray-900">{{ $release->version }}</span>
                        <span class="ml-2 text-gray-400">{{ $release->deployed_at->diffForHumans() }}</span>
                        @if($release->notes)
                            <span class="ml-2 text-gray-500">{{ $release->notes }}</span>
                        @endif
                    </div>
                    @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                        <form method="POST" action="{{ route('organizations.projects.releases.destroy', [$organization, $project, $release]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 hover:underline">Remove</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-gray-400">No releases marked yet.</p>
            @endforelse
        </div>
    </x-card>

    <livewire:project-issue-list :organization="$organization" :project="$project" />
@endsection
