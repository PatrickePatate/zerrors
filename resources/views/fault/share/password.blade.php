@extends('layouts.guest')

@section('title', 'Password required · Zerrors')

@section('content')
    <x-card class="text-center">
        <x-lucide-lock class="mx-auto h-8 w-8 text-gray-400" />
        <h1 class="mt-2 text-lg font-semibold text-gray-900">This issue is password protected</h1>
        <p class="mt-2 text-sm text-gray-500">Enter the password to view it.</p>

        <form method="POST" action="{{ route('share.unlock', $link->token) }}" class="mt-5 space-y-3 text-left">
            @csrf
            <x-form.text-input type="password" name="password" placeholder="Password" required autofocus />
            @error('password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <x-button type="submit" class="w-full justify-center">View issue</x-button>
        </form>
    </x-card>
@endsection
