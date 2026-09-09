@extends('layouts.app')

@section('title', 'Security · Zerrors')

@php($user = auth()->user())

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-900">Security</h1>

    @if(session('status'))
        <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <x-card class="mb-6">
        <h2 class="mb-1 text-sm font-medium text-gray-700">Two-factor authentication</h2>
        <p class="mb-3 text-sm text-gray-500">Protect your account with a TOTP authenticator app (Google Authenticator, 1Password, Authy&hellip;).</p>

        @if($user->hasTwoFactorEnabled())
            <p class="mb-3 text-sm text-green-700">Two-factor authentication is enabled.</p>

            @if(session('two_factor_recovery_codes'))
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="mb-2 font-medium">Save these recovery codes — each can be used once if you lose access to your authenticator:</p>
                    <div class="grid grid-cols-2 gap-1 font-mono text-xs">
                        @foreach(session('two_factor_recovery_codes') as $code)
                            <span>{{ $code }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('two-factor.disable') }}" class="flex items-end gap-3">
                @csrf
                @method('DELETE')
                <div class="max-w-xs">
                    <x-input label="Current password" type="password" name="password" :error="$errors->first('password')" required />
                </div>
                <x-button type="submit" variant="secondary">Disable 2FA</x-button>
            </form>
        @else
            @if(session('two_factor_secret'))
                <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p class="mb-1">Add this to your authenticator app manually, or paste the setup link:</p>
                    <code class="mb-2 block break-all rounded bg-white px-2 py-1 text-xs">{{ session('two_factor_secret') }}</code>
                    <code class="block break-all rounded bg-white px-2 py-1 text-xs">{{ session('two_factor_otp_url') }}</code>
                </div>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex items-end gap-3">
                    @csrf
                    <div class="w-40">
                        <x-input label="Confirm code" name="code" :error="$errors->first('code')" required autofocus />
                    </div>
                    <x-button type="submit">Confirm & enable</x-button>
                </form>
            @else
                <form method="POST" action="{{ route('two-factor.setup') }}">
                    @csrf
                    <x-button type="submit">Set up two-factor authentication</x-button>
                </form>
            @endif
        @endif
    </x-card>

    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
            <h2 class="text-sm font-medium text-gray-700">API tokens</h2>
        </div>

        <div class="border-b border-gray-100 px-5 py-3">
            @if(session('plain_text_token'))
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="mb-1 font-medium">Copy this token now — it won't be shown again:</p>
                    <code class="block break-all rounded bg-white px-2 py-1 text-xs">{{ session('plain_text_token') }}</code>
                </div>
            @endif

            <form method="POST" action="{{ route('security.tokens.store') }}" class="flex items-end gap-3">
                @csrf
                <div class="max-w-xs flex-1">
                    <x-input label="Token name" name="name" placeholder="CI script" :error="$errors->first('name')" required />
                </div>
                <x-button type="submit">Create token</x-button>
            </form>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($tokens as $token)
                <div class="flex items-center justify-between px-5 py-2 text-sm">
                    <div>
                        <span class="font-medium text-gray-900">{{ $token->name }}</span>
                        <span class="ml-2 text-gray-400">
                            {{ $token->last_used_at ? 'last used '.$token->last_used_at->diffForHumans() : 'never used' }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('security.tokens.destroy', $token->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-600 hover:underline">Revoke</button>
                    </form>
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-gray-400">No API tokens yet.</p>
            @endforelse
        </div>
    </x-card>
@endsection
