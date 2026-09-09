<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', ['invite' => $this->pendingInvite($request)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        $invite = $this->pendingInvite($request);

        if ($invite) {
            $invite->organization->users()->syncWithoutDetaching([$user->id => ['role' => $invite->role]]);
            $invite->forceFill(['accepted_at' => now()])->save();
        } else {
            $organization = Organization::create(['name' => "{$user->name}'s Organization"]);
            $organization->users()->attach($user->id, ['role' => 'owner']);
        }

        Auth::login($user);
        $request->session()->forget('invite_token');

        return redirect()->route('dashboard');
    }

    protected function pendingInvite(Request $request): ?OrganizationInvite
    {
        $token = $request->query('invite', $request->session()->get('invite_token'));

        if (! $token) {
            return null;
        }

        $invite = OrganizationInvite::where('token', $token)->first();

        return $invite && $invite->isPending() ? $invite : null;
    }
}
