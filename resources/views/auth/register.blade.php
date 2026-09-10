@extends('layouts.guest')

@section('title', 'Sign up · Zerrors')

@section('content')
    <x-card>
        @if($invite)
            <div class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                You've been invited to join <strong>{{ $invite->organization->name }}</strong> as {{ $invite->role }}.
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            @if($invite)
                <input type="hidden" name="invite" value="{{ $invite->token }}">
            @endif

            <x-form.text-input label="Name" name="name" value="{{ old('name') }}" :error="$errors->first('name')" required autofocus />
            <x-form.text-input label="Email" type="email" name="email" value="{{ old('email', $invite->email ?? '') }}" :error="$errors->first('email')" required />
            <x-form.text-input label="Password" type="password" name="password" :error="$errors->first('password')" required />
            <x-form.text-input label="Confirm password" type="password" name="password_confirmation" required />

            <x-button type="submit" class="w-full">Create account</x-button>
        </form>
    </x-card>
    <p class="mt-4 text-center text-sm text-gray-500">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-gray-900 hover:underline">Log in</a>
    </p>
@endsection
