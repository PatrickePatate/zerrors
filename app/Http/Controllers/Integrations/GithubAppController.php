<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Github\GithubAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GithubAppController extends Controller
{
    public function __construct(protected GithubAppClient $github) {}

    /**
     * Authorization is handled by the "org.member" middleware on this route.
     */
    public function redirect(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        return redirect()->away($this->github->installUrl($organization->id));
    }

    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'installation_id' => ['required', 'string'],
            'setup_action' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $organization = Organization::findOrFail($this->github->decryptState($data['state']));

        $installation = $this->github->installation($data['installation_id']);

        $organization->update([
            'github_installation_id' => $data['installation_id'],
            'github_account_login' => $installation['account']['login'] ?? null,
            'github_account_type' => $installation['account']['type'] ?? null,
            'github_connected_at' => now(),
        ]);

        return redirect()->route('organizations.settings.edit', $organization)
            ->with('status', 'GitHub connected.');
    }

    public function disconnect(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        $organization->update([
            'github_installation_id' => null,
            'github_account_login' => null,
            'github_account_type' => null,
            'github_connected_at' => null,
        ]);

        return back()->with('status', 'GitHub disconnected.');
    }

    protected function authorizeManage(Request $request, Organization $organization): void
    {
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);
    }
}
