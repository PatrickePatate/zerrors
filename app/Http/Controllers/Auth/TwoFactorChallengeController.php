<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->session()->has('2fa_user_id'), 404);

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        abort_unless($userId, 404);

        $user = User::findOrFail($userId);

        $data = $request->validate(['code' => ['required', 'string']]);

        $validCode = (new Google2FA)->verifyKey($user->two_factor_secret, str_replace(' ', '', $data['code']));
        $validRecoveryCode = $this->consumeRecoveryCode($user, $data['code']);

        if (! $validCode && ! $validRecoveryCode) {
            return back()->withErrors(['code' => 'That code is invalid.']);
        }

        Auth::login($user, (bool) $request->session()->pull('2fa_remember', false));
        $request->session()->forget('2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = json_decode($user->two_factor_recovery_codes ?? '[]', true) ?: [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(array_values(array_diff($codes, [$code]))),
        ])->save();

        return true;
    }
}
