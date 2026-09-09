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
        </div>

        <div class="flex items-center gap-2">
            <label for="assignedToUserId" class="text-sm text-gray-500">Assigned to</label>
            <select id="assignedToUserId" wire:model.live="assignedToUserId"
                    class="rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                <option value="">Unassigned</option>
                @foreach($members as $member)
                    <option value="{{ $member->id }}">{{ $member->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($githubError)
        <p class="mt-3 text-sm text-red-600">{{ $githubError }}</p>
    @endif

    <div class="mt-3 border-t border-gray-100 pt-3">
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
</div>
