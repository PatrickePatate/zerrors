@extends('layouts.guest')

@section('title', 'Join organization · Zerrors')

@section('content')
    <x-card class="text-center">
        <h1 class="text-lg font-semibold text-gray-900">Join {{ $invite->organization->name }}</h1>
        <p class="mt-2 text-sm text-gray-500">
            You've been invited as <strong>{{ $invite->role }}</strong>. Log in or create an account with
            <strong>{{ $invite->email }}</strong> to accept.
        </p>
        <div class="mt-5 flex justify-center gap-2">
            <x-button href="{{ route('register', ['invite' => $invite->token]) }}">Create account</x-button>
            <x-button variant="secondary" href="{{ route('login') }}">Log in instead</x-button>
        </div>
    </x-card>
@endsection
