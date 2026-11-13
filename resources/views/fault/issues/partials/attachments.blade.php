@php($attachments = $currentEvent->attachments()->orderBy('id')->get())

@if($attachments->isNotEmpty())
    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Attachments</h2>
        <ul class="divide-y divide-gray-100">
            @foreach($attachments as $attachment)
                <li class="flex items-center justify-between gap-3 py-2 text-sm first:pt-0 last:pb-0">
                    <span class="min-w-0 truncate text-gray-700">{{ $attachment->filename }}</span>
                    <span class="flex shrink-0 items-center gap-3 text-gray-400">
                        {{ \Illuminate\Support\Number::fileSize($attachment->size) }}
                        <a href="{{ route('organizations.attachments.download', [$organization, $project, $attachment]) }}" class="text-gray-600 hover:text-gray-900 hover:underline">Download</a>
                    </span>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif
