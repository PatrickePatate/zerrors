@extends('layouts.app')

@section('title', 'Your organizations · Zerrors')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">Your organizations</h1>

    <x-card class="mb-6">
        <h2 class="mb-3 text-sm font-medium text-gray-700">New organization</h2>
        <form method="POST" action="{{ route('organizations.store') }}" class="flex items-end gap-3">
            @csrf
            <div class="max-w-xs flex-1">
                <x-input name="name" placeholder="Acme Inc." :error="$errors->first('name')" required />
            </div>
            <x-button type="submit">Create</x-button>
        </form>
    </x-card>

    <x-card :padding="false">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-5 py-3 font-medium">Organization</th>
                    <th class="px-5 py-3 font-medium">Your role</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($organizations as $organization)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('organizations.projects.index', $organization) }}" class="font-medium text-gray-900 hover:underline">{{ $organization->name }}</a>
                        </td>
                        <td class="px-5 py-3"><x-badge color="indigo">{{ $organization->pivot->role }}</x-badge></td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-5 py-6 text-sm text-gray-400">You're not part of any organization yet — create one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
@endsection
