@extends('layouts.guest')

@section('title', 'Reset password · Zerrors')

@section('content')
    <x-card>
        <p class="mb-4 text-sm text-gray-500">
            Enter your email and we'll send you a link to reset your password.
        </p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-input label="Email" type="email" name="email" value="{{ old('email') }}" :error="$errors->first('email')" required autofocus />
            <x-button type="submit" class="w-full">Email password reset link</x-button>
        </form>
    </x-card>
    <p class="mt-4 text-center text-sm text-gray-500">
        <a href="{{ route('login') }}" class="font-medium text-gray-900 hover:underline">Back to login</a>
    </p>
@endsection
