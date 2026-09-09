@extends('layouts.guest')

@section('title', 'Log in · Zerrors')

@section('content')
    <x-card>
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-input label="Email" type="email" name="email" value="{{ old('email') }}" :error="$errors->first('email')" required autofocus />

            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" required
                       class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="remember" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900/10">
                    Remember me
                </label>
                <a href="{{ route('password.request') }}" class="text-sm text-gray-500 hover:text-gray-900 hover:underline">Forgot password?</a>
            </div>

            <x-button type="submit" class="w-full">Log in</x-button>
        </form>
    </x-card>
    <p class="mt-4 text-center text-sm text-gray-500">
        No account yet? <a href="{{ route('register') }}" class="font-medium text-gray-900 hover:underline">Sign up</a>
    </p>
@endsection
