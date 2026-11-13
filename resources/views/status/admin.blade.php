@extends('layouts.app')

@section('title', 'Status page · '.$organization->name.' · Zerrors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Status page</h1>
        <x-button :href="route('organizations.monitors.index', $organization)" tag="a" variant="secondary">Back to monitoring</x-button>
    </div>

    <livewire:status-page-manager :organization="$organization" />
@endsection
