@extends('layouts.guest')

@section('title', 'Reset password · Zerrors')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-form.text-input label="Email" type="email" name="email" value="{{ old('email', $email) }}" :error="$errors->first('email')" required autofocus />
            <x-form.text-input label="New password" type="password" name="password" :error="$errors->first('password')" required />
            <x-form.text-input label="Confirm new password" type="password" name="password_confirmation" required />
            <x-button type="submit" class="w-full">Reset password</x-button>
        </form>
    </x-card>
@endsection
