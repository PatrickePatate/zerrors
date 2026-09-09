<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request, Organization $organization): View
    {
        return view('fault.organizations.settings', [
            'organization' => $organization,
            'myRole' => $organization->roleFor($request->user()),
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorizeManage($request, $organization);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'alerts_enabled' => ['sometimes', 'boolean'],
            'require_2fa' => ['sometimes', 'boolean'],
        ]);
        $data['alerts_enabled'] = $request->boolean('alerts_enabled');
        $data['require_2fa'] = $request->boolean('require_2fa');

        $organization->update($data);

        app(AuditLogger::class)->log($organization, $request->user(), 'organization.updated');

        return back()->with('status', 'Organization updated.');
    }

    public function updateAi(Request $request, Organization $organization)
    {
        $this->authorizeManage($request, $organization);

        $data = $request->validate([
            'ai_provider' => ['required', 'in:'.implode(',', array_keys(Organization::AI_PROVIDERS))],
            'ai_api_key' => ['nullable', 'string'],
            'ai_model' => ['nullable', 'string', 'max:255'],
        ]);

        // Blank means "keep the existing key" — the field is never pre-filled with the real value.
        if (blank($data['ai_api_key'] ?? null)) {
            unset($data['ai_api_key']);
        }

        $organization->update($data);

        return back()->with('status', 'AI assistant settings updated.');
    }

    public function destroyAi(Request $request, Organization $organization)
    {
        $this->authorizeManage($request, $organization);

        $organization->update(['ai_provider' => null, 'ai_api_key' => null, 'ai_model' => null]);

        return back()->with('status', 'AI assistant disconnected.');
    }

    /**
     * Owners only. Wipes the organization and everything under it
     * (projects, issues, events, invites, memberships all cascade).
     */
    public function destroy(Request $request, Organization $organization)
    {
        abort_unless($organization->roleFor($request->user()) === 'owner', 403);

        $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($request->input('confirm_name') !== $organization->name) {
            return back()->withErrors(['confirm_name' => 'Type the organization name exactly to confirm deletion.']);
        }

        $organization->delete();

        return redirect()->route('dashboard')->with('status', "\"{$organization->name}\" has been deleted.");
    }

    /**
     * Any member can leave, unless they're the organization's last owner.
     */
    public function leave(Request $request, Organization $organization)
    {
        $user = $request->user();
        $role = $organization->roleFor($user);

        abort_unless($role !== null, 403);

        if ($role === 'owner' && $organization->users()->wherePivot('role', 'owner')->count() <= 1) {
            return back()->withErrors(['leave' => 'Transfer ownership to someone else before leaving — an organization needs at least one owner.']);
        }

        $organization->users()->detach($user->id);

        return redirect()->route('dashboard')->with('status', "You left \"{$organization->name}\".");
    }

    protected function authorizeManage(Request $request, Organization $organization): void
    {
        $role = $organization->roleFor($request->user());
        abort_unless(in_array($role, ['owner', 'admin'], true), 403);
    }
}
