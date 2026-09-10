@extends('layouts.app')

@section('title', $project->name.' · Zerrors')

@section('content')
    <a href="{{ route('organizations.projects.index', $organization) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> Projects
    </a>

    <x-card class="mb-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="-ms-1 text-sm font-normal">
                    <x-badge class="flex items-center gap-1.5 px-2 py-1">{{ $project->platform->icon('3') }} {{ $project->platform->label() }}</x-badge>
                </div>
                <h1 class="inline-flex items-start text-2xl py-1 font-normal text-gray-900">
                    {{ $project->name }}
                </h1>

                <div class="mt-1 text-sm text-gray-600">
                    DSN:
                    <x-credential :value="$project->dsn()" />
                </div>
            </div>
            <div class="flex items-center gap-2">
                <x-modal max-width="lg">
                    <x-slot:trigger>
                        <x-button type="button" variant="secondary">
                            <x-lucide-rocket class="h-4 w-4" />
                            Releases
                            @if($releases->isNotEmpty())
                                <span class="text-gray-400">&middot; {{ $releases->first()->version }}</span>
                            @endif
                        </x-button>
                    </x-slot:trigger>

                    <h2 class="text-sm font-medium text-gray-700">Releases</h2>

                    @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                        <form method="POST" action="{{ route('organizations.projects.releases.store', [$organization, $project]) }}" class="mt-3 flex items-end gap-2">
                            @csrf
                            <div class="w-32">
                                <x-form.text-input name="version" placeholder="v1.4.2" required />
                            </div>
                            <div class="w-48">
                                <x-form.text-input name="notes" placeholder="Notes (optional)" />
                            </div>
                            <x-button type="submit" variant="secondary" class="h-10 text-xs">Mark deploy</x-button>
                        </form>
                    @endif

                    <div class="-mx-6 mt-4 max-h-80 divide-y divide-gray-100 overflow-y-auto border-t border-gray-100">
                        @forelse($releases as $release)
                            <div class="flex items-center justify-between px-6 py-2 text-sm">
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
                            <p class="px-6 py-4 text-sm text-gray-400">No releases marked yet.</p>
                        @endforelse
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-button type="button" variant="secondary" @click="open = false">Close</x-button>
                    </div>
                </x-modal>

                @if(in_array($organization->roleFor(auth()->user()), ['owner', 'admin']))
                    <x-modal
                        :open-on-error="$errors->has('platform') || $errors->has('github_repo') || $errors->has('github_token') || $errors->has('production_branch') || $errors->has('github_webhook_secret') || $errors->has('forward_dsn')"
                        max-width="lg"
                    >
                        <x-slot:trigger>
                            <x-button type="button" variant="secondary">Edit project</x-button>
                        </x-slot:trigger>

                        <div x-data="{ tab: 'details' }">
                            <div class="mb-4 flex gap-1 border-b border-gray-200">
                                <button type="button" @click="tab = 'details'"
                                        :class="tab === 'details' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="border-b-2 px-3 pb-2 text-sm font-medium">Details</button>
                                <button type="button" @click="tab = 'notifications'"
                                        :class="tab === 'notifications' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="border-b-2 px-3 pb-2 text-sm font-medium">Notifications</button>
                                <button type="button" @click="tab = 'forwarding'"
                                        :class="tab === 'forwarding' ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="border-b-2 px-3 pb-2 text-sm font-medium">Forwarding</button>
                            </div>

                            <div x-show="tab === 'details'">
                                <form method="POST" action="{{ route('organizations.projects.settings.update', [$organization, $project]) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')
                                    <x-form.select label="Type" id="platform-edit" name="platform"
                                                    :options="collect(\App\Enums\FaultPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])"
                                                    :selected="$project->platform->value" />

                                    <div>
                                        <x-form.text-input label="GitHub repo (owner/repo)" name="github_repo" value="{{ $project->github_repo }}" placeholder="acme/api" :error="$errors->first('github_repo')" />
                                        <p class="mt-1 text-xs text-gray-400">Lets you create a linked GitHub issue straight from an issue page.</p>
                                    </div>
                                    <div>
                                        <x-form.text-input label="GitHub token" name="github_token" type="password" placeholder="{{ $project->github_token ? '••••••••' : 'ghp_…' }}" :error="$errors->first('github_token')" />
                                        <p class="mt-1 text-xs text-gray-400">Personal access token with "repo" scope. Leave blank to keep the current one.</p>
                                    </div>

                                    <div>
                                        <x-form.text-input label="Production branch" name="production_branch" value="{{ $project->production_branch }}" placeholder="main" :error="$errors->first('production_branch')" />
                                        <p class="mt-1 text-xs text-gray-400">The branch a release is recorded for when the GitHub webhook below receives a push.</p>
                                    </div>

                                    <div>
                                        <x-form.text-input label="GitHub webhook secret" name="github_webhook_secret" type="password" placeholder="{{ $project->github_webhook_secret ? '••••••••' : 'Generate one below' }}" :error="$errors->first('github_webhook_secret')" />
                                        <p class="mt-1 text-xs text-gray-400">Must match the secret configured on the GitHub webhook. Leave blank to keep the current one.</p>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                                        <x-button type="submit">Save</x-button>
                                    </div>
                                </form>

                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <h3 class="text-xs font-medium tracking-wide text-gray-600 uppercase">GitHub webhook</h3>
                                    <p class="mt-1 text-xs text-gray-400">
                                        Add this as a "push" webhook on the repository (Settings &rarr; Webhooks) so a release is
                                        created automatically whenever a PR is merged into the production branch above.
                                    </p>
                                    <div class="mt-2">
                                        <x-credential :value="$project->githubWebhookUrl()" />
                                    </div>

                                    @if(session('githubWebhookSecret'))
                                        <p class="mt-2 text-xs text-gray-500">Secret (shown once, copy it now):</p>
                                        <div class="mt-1">
                                            <x-credential :value="session('githubWebhookSecret')" />
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('organizations.projects.githubWebhookSecret.generate', [$organization, $project]) }}"
                                          @if($project->github_webhook_secret)
                                              x-data @submit="if (! confirm('Generate a new webhook secret? Update it on the GitHub webhook too, or pushes will stop creating releases.')) $event.preventDefault()"
                                          @endif
                                          class="mt-2">
                                        @csrf
                                        <x-button type="submit" variant="secondary" class="text-xs">
                                            {{ $project->github_webhook_secret ? 'Regenerate secret' : 'Generate secret' }}
                                        </x-button>
                                    </form>
                                </div>
                            </div>

                            <div x-show="tab === 'notifications'" x-cloak>
                                <livewire:notification-channel-manager :organization="$organization" :project="$project" :key="'notification-channels-'.$project->id" />

                                <div class="mt-4 flex justify-end">
                                    <x-button type="button" variant="secondary" @click="open = false">Close</x-button>
                                </div>
                            </div>

                            <div x-show="tab === 'forwarding'" x-cloak>
                                <form method="POST" action="{{ route('organizations.projects.forwarding.update', [$organization, $project]) }}" class="space-y-4">
                                    @csrf
                                    @method('PATCH')

                                    <p class="text-xs text-gray-500">
                                        Every event ingested for this project is also re-sent to another Sentry-compatible
                                        DSN, so you can run zerrors and Sentry side by side without changing the SDK config.
                                    </p>

                                    <div>
                                        <x-form.text-input label="Target DSN" name="forward_dsn" value="{{ old('forward_dsn', $project->forward_dsn) }}"
                                                            placeholder="https://<key>@o0.ingest.sentry.io/0" :error="$errors->first('forward_dsn')" />
                                    </div>

                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="forward_enabled" value="1" @checked($project->forward_enabled) class="rounded border-gray-300">
                                        Forward events to this DSN
                                    </label>

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
                @endif
            </div>
        </div>
    </x-card>

    <livewire:project-issue-list :organization="$organization" :project="$project" />
@endsection
