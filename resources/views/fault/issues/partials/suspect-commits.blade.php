@php($suspectCommits = app(\App\Support\Fault\SuspectCommitFinder::class)->find($issue))
@if($suspectCommits->isNotEmpty())
    <div class="mt-3 text-sm">
        <div class="text-gray-400"><x-lucide-git-commit-horizontal class="w-4 h-4 inline"/> Suspect commits</div>
        <ul class="mt-1 space-y-1">
            @foreach($suspectCommits as $commit)
                <li class="text-gray-500">
                    @if($commit->url)
                        <a href="{{ $commit->url }}" target="_blank" rel="noopener" class="font-mono underline">{{ $commit->shortSha() }}</a>
                    @else
                        <span class="font-mono">{{ $commit->shortSha() }}</span>
                    @endif
                    &middot; {{ $commit->author }} &middot; {{ $commit->firstLineOfMessage() }}
                    &middot; <code class="text-gray-400">{{ $commit->matched_file }}</code>
                </li>
            @endforeach
        </ul>
    </div>
@endif
