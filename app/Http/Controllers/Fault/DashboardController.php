<?php

namespace App\Http\Controllers\Fault;

use App\Enums\FaultPlatform;
use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Organization $organization): View
    {
        $projects = $organization->projects()->withCount([
            'issues',
            'issues as unresolved_issues_count' => fn ($q) => $q->where('status', 'unresolved'),
        ])->latest()->get();

        return view('fault.projects.index', ['organization' => $organization, 'projects' => $projects]);
    }

    public function store(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(FaultPlatform::class)],
        ]);

        $project = $organization->projects()->create($data);

        return redirect()->route('organizations.projects.show', [$organization, $project]);
    }

    public function updateSettings(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $data = $request->validate([
            'platform' => ['required', Rule::enum(FaultPlatform::class)],
            'github_repo' => ['nullable', 'string', 'regex:/^[\w.-]+\/[\w.-]+$/'],
            'github_token' => ['nullable', 'string'],
            'production_branch' => ['nullable', 'string', 'max:255'],
            'github_webhook_secret' => ['nullable', 'string'],
        ]);

        if (empty($data['github_token'])) {
            unset($data['github_token']);
        }

        if (empty($data['production_branch'])) {
            unset($data['production_branch']);
        }

        if (empty($data['github_webhook_secret'])) {
            unset($data['github_webhook_secret']);
        }

        $project->update($data);

        return back()->with('status', 'Project settings updated.');
    }

    public function updateNotifications(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $data = $request->validate([
            'slack_webhook_url' => ['nullable', 'url', 'max:2048'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'notify_email' => ['nullable', 'email', 'max:255'],
        ]);

        if (empty($data['telegram_bot_token'])) {
            unset($data['telegram_bot_token']);
        }

        $project->update($data);

        return back()->with('status', 'Notification settings updated.');
    }

    public function show(Request $request, Organization $organization, FaultProject $project): View
    {
        abort_unless($project->organization_id === $organization->id, 404);

        return view('fault.projects.show', [
            'organization' => $organization,
            'project' => $project,
            'releases' => $project->releases()->latest('deployed_at')->limit(10)->get(),
        ]);
    }

    /**
     * Regenerate the project's public key — useful once a DSN has leaked.
     * Every SDK using the old key will start getting 401s immediately.
     */
    public function rotateKey(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $project->update(['public_key' => Str::random(32)]);

        app(AuditLogger::class)->log($organization, $request->user(), 'project.key_rotated', $project->name);

        return back()->with('status', 'DSN key rotated — update it wherever this project\'s SDK is configured.');
    }

    /**
     * Generates a fresh GitHub webhook secret and flashes it once, uneditable
     * afterwards, so the admin can paste it into the GitHub webhook settings.
     */
    public function generateGithubWebhookSecret(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $secret = Str::random(40);

        $project->update(['github_webhook_secret' => $secret]);

        app(AuditLogger::class)->log($organization, $request->user(), 'project.github_webhook_secret_rotated', $project->name);

        return back()->with('status', 'New webhook secret generated.')->with('githubWebhookSecret', $secret);
    }
}
