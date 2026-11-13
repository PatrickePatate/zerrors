<x-card>
    <h2 class="mb-3 text-sm font-semibold text-gray-900">Activity</h2>

    @can('write', $organization)
    <form wire:submit="addComment" class="mb-4 space-y-2">
        <x-form.textarea name="body" wire:model="body" rows="3" placeholder="Leave a comment (markdown supported)&hellip;" :error="$errors->first('body')" />
        <x-button type="submit" wire:loading.attr="disabled" wire:target="addComment">Comment</x-button>
    </form>
    @endcan

    <ul class="space-y-3">
        @forelse($activities as $activity)
            @php
                $data = $activity->data ?? [];
                $actor = $activity->user?->name ?? 'System';
                $summary = match ($activity->type) {
                    'status' => $actor.' marked it '.($data['to'] ?? '?')
                        .(! empty($data['resolved_in_release']) ? ' in '.$data['resolved_in_release'] : '')
                        .(! empty($data['in_next_release']) ? ' in the next release' : '')
                        .(! empty($data['snoozed_until']) ? ' until '.\Illuminate\Support\Carbon::parse($data['snoozed_until'])->toDayDateTimeString() : '')
                        .(! empty($data['until_count']) ? ' until '.number_format($data['until_count']).' events' : ''),
                    'assignment' => $actor.(($data['to'] ?? null) ? ' assigned it to '.($members[$data['to']] ?? 'a former member') : ' unassigned it'),
                    'regression' => 'Regressed'.(! empty($data['release']) ? ' in release '.$data['release'] : ''),
                    'resurfaced' => 'Resurfaced ('.(($data['reason'] ?? '') === 'count_reached' ? 'event threshold reached' : 'snooze ended').')',
                    'github_linked' => $actor.' linked GitHub issue #'.($data['number'] ?? ''),
                    'merged' => $actor.' merged '.count($data['merged'] ?? []).' issue(s) into this one',
                    default => $actor.' '.$activity->type,
                };
            @endphp
            <li class="text-sm">
                @if($activity->type === 'comment')
                    <div class="flex items-center justify-between gap-2 text-xs text-gray-500">
                        <span><span class="font-medium text-gray-700">{{ $actor }}</span> commented &middot; {{ $activity->created_at?->diffForHumans() }}</span>
                        @if($activity->user_id === auth()->id())
                            <button type="button" wire:click="deleteComment({{ $activity->id }})" wire:confirm="Delete this comment?" class="text-gray-400 hover:text-red-600">Delete</button>
                        @endif
                    </div>
                    <div class="prose prose-sm mt-1 max-w-none text-gray-800">
                        {!! \Illuminate\Support\Str::markdown($data['body'] ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                    </div>
                @else
                    <p class="text-gray-500">{{ $summary }} &middot; <span class="text-xs text-gray-400">{{ $activity->created_at?->diffForHumans() }}</span></p>
                @endif
            </li>
        @empty
            <li class="text-sm text-gray-400">No activity yet.</li>
        @endforelse
    </ul>
</x-card>
