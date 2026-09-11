<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Slack\SlackAppClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlackAppController extends Controller
{
    public function __construct(protected SlackAppClient $slack) {}

    public function redirect(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        return redirect()->away($this->slack->authorizeUrl($organization->id));
    }

    public function callback(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $organization = Organization::findOrFail($this->slack->decryptState($data['state']));

        $response = $this->slack->exchangeCode($data['code']);

        $organization->update([
            'slack_team_id' => $response['team']['id'] ?? null,
            'slack_team_name' => $response['team']['name'] ?? null,
            'slack_bot_token' => $response['access_token'] ?? null,
            'slack_authed_user_id' => $response['authed_user']['id'] ?? null,
            'slack_connected_at' => now(),
        ]);

        return redirect()->route('organizations.settings.edit', $organization)
            ->with('status', 'Slack connected.');
    }

    public function disconnect(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeManage($request, $organization);

        $organization->update([
            'slack_team_id' => null,
            'slack_team_name' => null,
            'slack_bot_token' => null,
            'slack_authed_user_id' => null,
            'slack_connected_at' => null,
        ]);

        return back()->with('status', 'Slack disconnected.');
    }

    protected function authorizeManage(Request $request, Organization $organization): void
    {
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);
    }
}
