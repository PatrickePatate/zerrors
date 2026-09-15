@extends('layouts.guest')

@section('title', 'Link expired · Zerrors')

@section('content')
    <x-card class="text-center">
        <x-lucide-circle-alert class="mx-auto h-8 w-8 text-amber-500" />
        <h1 class="mt-2 text-lg font-semibold text-gray-900">This share link has expired</h1>
        <p class="mt-2 text-sm text-gray-500">Ask whoever shared it with you to send a new link.</p>
    </x-card>
@endsection
