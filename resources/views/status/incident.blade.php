<div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-medium">{{ $incident['title'] }}</h3>
        <span class="shrink-0 text-xs text-gray-400">{{ \Illuminate\Support\Carbon::parse($incident['started_at'])->format('M j, H:i') }} UTC</span>
    </div>
    <ul class="mt-2 space-y-2">
        @foreach(array_reverse($incident['updates']) as $update)
            <li class="text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ ucfirst($update['status']) }}</span>
                &middot; {{ $update['body'] }}
                <span class="text-xs text-gray-400">({{ \Illuminate\Support\Carbon::parse($update['created_at'])->format('M j, H:i') }})</span>
            </li>
        @endforeach
    </ul>
</div>
