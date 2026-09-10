@php($statusColors = ['unresolved' => 'red', 'resolved' => 'green', 'ignored' => 'gray'])

<div>
    <div class="mb-3">
        <x-badge :color="$statusColors[$issue->status] ?? 'gray'">{{ $issue->status }}</x-badge>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            <x-button type="button" wire:click="updateStatus('resolved')" wire:loading.attr="disabled">Resolve</x-button>
            <x-button type="button" wire:click="updateStatus('ignored')" wire:loading.attr="disabled" variant="secondary">Ignore</x-button>
            <x-button type="button" wire:click="updateStatus('unresolved')" wire:loading.attr="disabled" variant="secondary">Unresolve</x-button>
            @if($issue->github_issue_url)
                <a href="{{ $issue->github_issue_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:underline">
                    <x-lucide-github class="h-4 w-4" />
                    GitHub issue #{{ $issue->github_issue_number }}
                </a>
            @elseif($project->hasGithubConfigured())
                <button type="button" wire:click="createGithubIssue" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                    <x-lucide-github class="h-4 w-4" />
                    <span wire:loading.remove wire:target="createGithubIssue">Create GitHub issue</span>
                    <span wire:loading wire:target="createGithubIssue">Creating&hellip;</span>
                </button>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500">Assigned to</span>
            <div class="w-40">
                <x-form.select id="assignedToUserId" wire:model.live="assignedToUserId"
                                :options="['' => 'Unassigned'] + $members->pluck('name', 'id')->all()"
                                :selected="$assignedToUserId"
                                :error="$errors->first('assignedToUserId')" />
            </div>
        </div>
    </div>

    @if($githubError)
        <p class="mt-3 text-sm text-red-600">{{ $githubError }}</p>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
        @if($project->hasGithubConfigured() && $linkedRelease && ! $linkedRelease->commit_message)
            <button type="button" wire:click="fetchCommit" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-50">
                <x-lucide-git-commit-horizontal class="h-4 w-4" />
                <span wire:loading.remove wire:target="fetchCommit">Fetch linked commit</span>
                <span wire:loading wire:target="fetchCommit">Fetching&hellip;</span>
            </button>
        @endif
    </div>

    @if($event)
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-gray-500">
            @if($eventUser = $event->contextUser())
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide-user class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    {{ $eventUser['email'] ?? $eventUser['username'] ?? ('User #'.$eventUser['id']) }}
                </span>
            @endif

            @if($browser = $event->browserLabel())
                <span class="inline-flex items-center gap-1.5">
                    <x-lucide-monitor class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    {{ $browser }}
                    @if($event->isLikelyBot())
                        <x-badge color="amber">bot</x-badge>
                    @endif
                </span>
            @endif

            @if($contextUrl = $event->contextUrl())
                <span class="inline-flex min-w-0 items-center gap-1.5" title="{{ $contextUrl }}">
                    <x-lucide-link class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                    <span class="max-w-sm truncate">{{ $contextUrl }}</span>
                </span>
            @endif
        </div>
    @endif

    @if($commitError)
        <p class="mt-2 text-sm text-red-600">{{ $commitError }}</p>
    @endif

    @if($linkedRelease && $linkedRelease->commit_sha)
        <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-gray-700">Suspect commit <span class="font-normal text-gray-400">(release {{ $linkedRelease->version }})</span></span>
                @if($linkedRelease->commit_url)
                    <a href="{{ $linkedRelease->commit_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:underline">
                        <x-lucide-github class="h-3.5 w-3.5" />
                        View on GitHub
                    </a>
                @endif
            </div>

            <p class="mt-1 font-mono text-xs text-gray-500">{{ Str::limit($linkedRelease->commit_sha, 12, '') }}</p>

            @if($linkedRelease->commit_message)
                <p class="mt-1 text-gray-800">{{ Str::of($linkedRelease->commit_message)->before("\n") }}</p>
            @endif

            <p class="mt-1 text-xs text-gray-400">
                @if($linkedRelease->commit_author) by {{ $linkedRelease->commit_author }} @endif
                @if($linkedRelease->committed_at) &middot; {{ $linkedRelease->committed_at->diffForHumans() }} @endif
                @if($linkedRelease->commit_additions !== null)
                    &middot; <span class="text-green-600">+{{ $linkedRelease->commit_additions }}</span> <span class="text-red-600">-{{ $linkedRelease->commit_deletions }}</span>
                @endif
            </p>

            @if(! empty($linkedRelease->commit_files))
                <ul class="mt-2 space-y-0.5 font-mono text-xs text-gray-500">
                    @foreach($linkedRelease->commit_files as $file)
                        <li>{{ $file['status'] }} &middot; {{ $file['filename'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
