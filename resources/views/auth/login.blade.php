@extends('layouts.guest')

@section('title', 'Log in · Zerrors')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-form.text-input label="Email" type="email" name="email" value="{{ old('email') }}" :error="$errors->first('email')" required autofocus />

            <x-form.text-input label="Password" type="password" name="password" required />

            <div class="flex items-center justify-between">
                <x-form.checkbox name="remember">Remember me</x-form.checkbox>
                <a href="{{ route('password.request') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">Forgot password?</a>
            </div>

            <x-button type="submit" class="w-full">Log in</x-button>
        </form>
    </x-card>
    <p class="mt-4 text-center text-sm text-gray-500">
        No account yet? <a href="{{ route('register') }}" class="font-medium text-gray-900 hover:underline">Sign up</a>
    </p>
@endsection
