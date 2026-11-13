@php($facets = app(\App\Support\Fault\IssueTagFacets::class)->for($issue))

@if($facets !== [])
    <x-card>
        <h2 class="mb-3 text-sm font-medium text-gray-700">Top tags</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($facets as $facet)
                <div>
                    <h3 class="mb-1 text-xs font-medium tracking-wide text-gray-400 uppercase">{{ $facet['key'] }}</h3>
                    <ul class="space-y-1.5">
                        @foreach($facet['values'] as $row)
                            <li class="text-sm">
                                <div class="flex justify-between gap-2 text-gray-700">
                                    <span class="truncate">{{ $row['value'] }}</span>
                                    <span class="shrink-0 text-gray-400">{{ $row['percent'] }}%</span>
                                </div>
                                <div class="h-1 rounded bg-gray-100"><div class="h-1 rounded bg-indigo-400" style="width: {{ $row['percent'] }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-card>
@endif
