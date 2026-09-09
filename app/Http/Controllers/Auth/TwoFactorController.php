<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * Generate (or regenerate) a pending secret for the user to scan/enter.
     * Not enabled until confirm() verifies a code against it.
     */
    public function setup(Request $request)
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = rawurlencode(config('app.name'));
        $label = rawurlencode("{$issuer}:{$request->user()->email}");
        $otpAuthUrl = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";

        return back()->with([
            'two_factor_secret' => $secret,
            'two_factor_otp_url' => $otpAuthUrl,
        ]);
    }

    public function confirm(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! $user->two_factor_secret || ! (new Google2FA)->verifyKey($user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => 'That code is invalid.']);
        }

        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::random(10))->all();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ])->save();

        return back()->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', 'Two-factor authentication disabled.');
    }
}
