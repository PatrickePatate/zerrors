@extends('layouts.app')

@section('title', $organization->name.' · Zerrors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Errors (24h)" :value="$stats['events_24h']" :color="$stats['events_24h'] > 0 ? 'red' : 'gray'" />
        <x-stat-card label="New issues (24h)" :value="$stats['new_issues_24h']" :color="$stats['new_issues_24h'] > 0 ? 'amber' : 'gray'" />
        <x-stat-card label="Unresolved issues" :value="$stats['unresolved_issues']" :color="$stats['unresolved_issues'] > 0 ? 'red' : 'green'" />
        <x-stat-card label="Projects" :value="$stats['projects']" />
    </div>

    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-sm font-medium text-gray-700">Recent issues</h2>
        <a href="{{ route('organizations.projects.index', $organization) }}" class="text-sm text-gray-500 hover:underline">View all projects</a>
    </div>

    <livewire:recent-issue-list :organization="$organization" />
@endsection
