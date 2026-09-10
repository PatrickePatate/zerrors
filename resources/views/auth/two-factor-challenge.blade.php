@extends('layouts.guest')

@section('title', 'Two-factor authentication · Zerrors')

@section('content')
    <x-card>
        <p class="mb-4 text-sm text-gray-600">Enter the code from your authenticator app, or one of your recovery codes.</p>
        <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="space-y-4">
            @csrf
            <x-form.text-input label="Code" name="code" :error="$errors->first('code')" required autofocus autocomplete="one-time-code" />
            <x-button type="submit" class="w-full">Verify</x-button>
        </form>
    </x-card>
@endsection
