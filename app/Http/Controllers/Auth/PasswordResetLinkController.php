<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Always respond the same way whether or not the email exists, so this
        // can't be used to enumerate registered accounts.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account exists for that email, a password reset link is on its way.');
    }
}
