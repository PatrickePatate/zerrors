@php($feedback = $issue->feedback()->latest()->limit(20)->get())

@if($feedback->isNotEmpty())
    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">User feedback</h2>
        <ul class="divide-y divide-gray-100">
            @foreach($feedback as $item)
                <li class="py-3 first:pt-0 last:pb-0">
                    <p class="text-sm text-gray-900 whitespace-pre-line">{{ $item->comments }}</p>
                    <p class="mt-1 text-xs text-gray-400">
                        {{ $item->name ?: 'Anonymous' }}@if($item->email) &middot; {{ $item->email }}@endif
                        &middot; {{ $item->created_at->diffForHumans() }}
                    </p>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
