@extends('layouts.app')

@section('title', $monitor->name.' · '.$organization->name.' · Zerrors')

@section('content')
    <a href="{{ route('organizations.monitors.index', $organization) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> Monitoring
    </a>

    <livewire:monitor-detail :organization="$organization" :monitor="$monitor" />
@endsection
