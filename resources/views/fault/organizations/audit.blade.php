@extends('layouts.app')

@section('title', 'Audit log · '.$organization->name)

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">{{ $organization->name }} &middot; Audit log</h1>

    @php
        $actionColors = [
            'deleted' => 'red',
            'removed' => 'red',
            'key_rotated' => 'amber',
            'created' => 'green',
            'received' => 'green',
            'transferred' => 'blue',
            'updated' => 'indigo',
            'role_updated' => 'indigo',
        ];

        $colorFor = function (string $action) use ($actionColors) {
            $suffix = str($action)->afterLast('.')->toString();

            return $actionColors[$suffix] ?? 'gray';
        };
    @endphp

    <x-table>
        <x-table.head>
            <x-table.column>Action</x-table.column>
            <x-table.column>Subject</x-table.column>
            <x-table.column>Details</x-table.column>
            <x-table.column>By</x-table.column>
            <x-table.column>When</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($logs as $log)
                <x-table.row>
                    <x-table.cell><x-badge :color="$colorFor($log->action)">{{ str($log->action)->replace('.', ' ')->replace('_', ' ') }}</x-badge></x-table.cell>
                    <x-table.cell class="text-gray-700">{{ $log->subject_label ?? '—' }}</x-table.cell>
                    <x-table.cell class="max-w-xs text-gray-500">
                        @if(! empty($log->meta))
                            <span class="font-mono text-xs">
                                {{ collect($log->meta)->map(fn ($value, $key) => "{$key}: ".(is_scalar($value) ? $value : json_encode($value)))->implode(', ') }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </x-table.cell>
                    <x-table.cell class="text-gray-500">{{ $log->user?->name ?? 'System' }}</x-table.cell>
                    <x-table.cell class="text-gray-400">
                        <x-tooltip :message="$log->created_at->format('Y-m-d H:i:s')">
                            {{ $log->created_at->diffForHumans() }}
                        </x-tooltip>
                    </x-table.cell>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5">No activity recorded yet.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>

    <div class="mt-4">
        <x-pagination :paginator="$logs" :livewire="false" />
    </div>
@endsection
