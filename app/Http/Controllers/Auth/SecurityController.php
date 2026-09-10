<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.security', [
            'tokens' => $request->user()->tokens()->latest()->get(),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPath = $user->avatar_path;

        $user->update(['avatar_path' => $data['avatar']->store('avatars', 'public')]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'Profile picture updated.');
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return back()->with('status', 'Profile picture removed.');
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
