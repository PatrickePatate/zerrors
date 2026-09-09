<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.security', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function storeToken(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $token = $request->user()->createToken($data['name']);

        return back()->with('plain_text_token', $token->plainTextToken);
    }

    public function destroyToken(Request $request, string $tokenId)
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', 'Token revoked.');
    }
}
