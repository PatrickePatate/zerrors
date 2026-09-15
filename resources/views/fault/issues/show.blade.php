@extends('layouts.app')

@section('title', $issue->title.' · Zerrors')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
            <x-lucide-arrow-left class="h-4 w-4" /> {{ $project->name }}
        </a>

        <livewire:issue-share-modal :organization="$organization" :project="$project" :issue="$issue" />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('fault.issues._detail', ['isGuest' => false])
        </div>

        <div class="lg:sticky lg:top-6 lg:self-start">
            <livewire:issue-analysis :issue="$issue" />
        </div>
    </div>
@endsection
