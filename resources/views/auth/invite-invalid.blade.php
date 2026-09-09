@extends('layouts.guest')

@section('title', 'Invite expired · Zerrors')

@section('content')
    <x-card class="text-center">
        <x-lucide-circle-alert class="mx-auto h-8 w-8 text-amber-500" />
        <h1 class="mt-2 text-lg font-semibold text-gray-900">This invite is no longer valid</h1>
        <p class="mt-2 text-sm text-gray-500">It may have already been used, revoked, or expired. Ask an organization admin to send a new one.</p>
        <x-button href="{{ route('login') }}" class="mt-5">Back to login</x-button>
    </x-card>
@endsection
