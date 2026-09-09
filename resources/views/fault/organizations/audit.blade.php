@extends('layouts.app')

@section('title', 'Audit log · '.$organization->name)

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">{{ $organization->name }} &middot; Audit log</h1>

    <x-card :padding="false">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3 font-medium">Action</th>
                    <th class="px-5 py-3 font-medium">Subject</th>
                    <th class="px-5 py-3 font-medium">By</th>
                    <th class="px-5 py-3 font-medium">When</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3"><x-badge>{{ $log->action }}</x-badge></td>
                        <td class="px-5 py-3 text-gray-700">{{ $log->subject_label ?? '—' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-5 py-3 text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-sm text-gray-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
