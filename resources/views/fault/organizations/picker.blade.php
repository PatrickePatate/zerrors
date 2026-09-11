@extends('layouts.app')

@section('title', 'Your organizations · Zerrors')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">Your organizations</h1>

    <x-card class="mb-6">
        <h2 class="mb-3 text-sm font-medium text-gray-700">New organization</h2>
        <form method="POST" action="{{ route('organizations.store') }}" class="flex items-end gap-3">
            @csrf
            <div class="max-w-xs flex-1">
                <x-form.text-input name="name" placeholder="Acme Inc." :error="$errors->first('name')" required />
            </div>
            <x-button type="submit">Create</x-button>
        </form>
    </x-card>

    <x-table>
        <x-table.head>
            <x-table.column>Organization</x-table.column>
            <x-table.column>Your role</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($organizations as $organization)
                <x-table.row>
                    <x-table.cell>
                        <a href="{{ route('organizations.projects.index', $organization) }}" class="font-medium text-gray-900 hover:underline">{{ $organization->name }}</a>
                    </x-table.cell>
                    <x-table.cell><x-badge color="indigo">{{ $organization->pivot->role }}</x-badge></x-table.cell>
                </x-table.row>
            @empty
                <x-table.empty :colspan="2">You're not part of any organization yet — create one above.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>
@endsection
