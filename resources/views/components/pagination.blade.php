@props(['paginator', 'livewire' => true])

<div {{ $attributes->class(['flex items-center justify-between w-full']) }}>
    <p class="text-sm text-gray-500">
        @if($paginator->total() > 0)
            Showing <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span>
            to <span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-gray-700">{{ $paginator->total() }}</span> results
        @else
            No results
        @endif
    </p>

    <nav>
        <ul class="flex items-center h-9 text-sm leading-tight bg-white border divide-x rounded text-neutral-500 divide-neutral-200 border-neutral-200">
            <li class="h-full">
                @if($paginator->onFirstPage())
                    <span class="relative inline-flex h-full cursor-not-allowed items-center rounded-l px-3 text-neutral-300">Previous</span>
                @elseif($livewire)
                    <button type="button" wire:click="previousPage" class="relative inline-flex h-full items-center rounded-l px-3 hover:bg-gray-50 hover:text-neutral-900">Previous</button>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex h-full items-center rounded-l px-3 hover:bg-gray-50 hover:text-neutral-900">Previous</a>
                @endif
            </li>

            @foreach($paginator->getUrlRange(1, max($paginator->lastPage(), 1)) as $page => $url)
                <li class="hidden h-full md:block">
                    @if($page == $paginator->currentPage())
                        <span class="relative inline-flex h-full items-center bg-gray-50 px-3 text-neutral-900">
                            {{ $page }}
                            <span class="box-content absolute bottom-0 left-0 -mx-px h-px w-full translate-y-px border-l border-r border-neutral-900 bg-neutral-900"></span>
                        </span>
                    @elseif($livewire)
                        <button type="button" wire:click="gotoPage({{ $page }})" class="relative inline-flex h-full items-center px-3 hover:bg-gray-50 hover:text-neutral-900">{{ $page }}</button>
                    @else
                        <a href="{{ $url }}" class="relative inline-flex h-full items-center px-3 hover:bg-gray-50 hover:text-neutral-900">{{ $page }}</a>
                    @endif
                </li>
            @endforeach

            <li class="h-full">
                @if(! $paginator->hasMorePages())
                    <span class="relative inline-flex h-full cursor-not-allowed items-center rounded-r px-3 text-neutral-300">Next</span>
                @elseif($livewire)
                    <button type="button" wire:click="nextPage" class="relative inline-flex h-full items-center rounded-r px-3 hover:bg-gray-50 hover:text-neutral-900">Next</button>
                @else
                    <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex h-full items-center rounded-r px-3 hover:bg-gray-50 hover:text-neutral-900">Next</a>
                @endif
            </li>
        </ul>
    </nav>
</div>
