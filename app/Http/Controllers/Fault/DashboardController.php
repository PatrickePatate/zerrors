<?php

namespace App\Http\Controllers\Fault;

use App\Enums\FaultPlatform;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Http\Controllers\Controller;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Sentry\Dsn;

class DashboardController extends Controller
{
    public function overview(Organization $organization): View
    {
        $stats = Cache::remember("organization:{$organization->id}:dashboard-stats", 60, function () use ($organization) {
            $projectIds = $organization->projects()->pluck('id');

            $since24h = now()->subDay();

            return [
                'events_24h' => FaultEvent::whereIn('fault_project_id', $projectIds)->where('occurred_at', '>=', $since24h)->count(),
                'new_issues_24h' => FaultIssue::whereIn('fault_project_id', $projectIds)->where('first_seen_at', '>=', $since24h)->count(),
                'unresolved_issues' => FaultIssue::whereIn('fault_project_id', $projectIds)->where('status', 'unresolved')->count(),
                'projects' => $projectIds->count(),
            ];
        });

        return view('fault.dashboard.index', [
            'organization' => $organization,
            'stats' => $stats,
        ]);
    }

    public function index(Organization $organization): View
    {
        $projects = $organization->projects()->withCount([
            'issues',
            'issues as unresolved_issues_count' => fn ($q) => $q->where('status', 'unresolved'),
        ])->latest()->paginate(25);

        return view('fault.projects.index', ['organization' => $organization, 'projects' => $projects]);
    }

    public function store(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(FaultPlatform::class)],
        ]);

        $project = $organization->projects()->create($data);

        $channel = $project->notificationChannels()->create([
            'type' => NotificationChannelType::Email,
            'name' => 'Email',
            'config' => ['email' => $request->user()->email],
            'enabled' => true,
        ]);

        $channel->rules()->create([
            'trigger' => NotificationRuleTrigger::OccurrenceThreshold,
            // 1 is intentionally excluded: notify() already emails the org for the first
            // occurrence, so including it here would double-notify this channel's recipient.
            'thresholds' => [10, 100, 1000],
        ]);

        return redirect()->route('organizations.projects.show', [$organization, $project]);
    }

    public function updateSettings(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $data = $request->validate([
            'platform' => ['required', Rule::enum(FaultPlatform::class)],
            'github_repo' => ['nullable', 'string', 'regex:/^[\w.-]+\/[\w.-]+$/'],
            'production_branch' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['production_branch'])) {
            unset($data['production_branch']);
        }

        $project->update($data);

        return back()->with('status', 'Project settings updated.');
    }

    public function updateForwarding(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $data = $request->validate([
            'forward_enabled' => ['sometimes', 'boolean'],
            'forward_dsn' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['forward_enabled'] = $request->boolean('forward_enabled');

        if (array_key_exists('forward_dsn', $data) && $data['forward_dsn'] !== null) {
            try {
                Dsn::createFromString($data['forward_dsn']);
            } catch (InvalidArgumentException) {
                return back()->withErrors(['forward_dsn' => 'That does not look like a valid Sentry DSN.']);
            }
        }

        $project->update($data);

        return back()->with('status', 'Forwarding settings updated.');
    }

    public function updateCensorship(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        if ($request->boolean('reset')) {
            $project->update(['censored_headers' => null]);

            return back()->with('status', 'Censorship settings reset to the default header list.');
        }

        $data = $request->validate([
            'censored_headers' => ['nullable', 'string'],
        ]);

        $headers = collect(preg_split('/[\r\n,]+/', $data['censored_headers'] ?? ''))
            ->map(fn (string $header) => trim($header))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $project->update(['censored_headers' => $headers]);

        return back()->with('status', 'Censorship settings updated.');
    }

    /**
     * Move a project to another organization the user owns.
     */
    public function transfer(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($organization->roleFor($request->user()) === 'owner', 403);

        $data = $request->validate([
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
        ]);

        abort_if((int) $data['organization_id'] === $organization->id, 422, 'Choose a different organization.');

        $destination = Organization::findOrFail($data['organization_id']);

        abort_unless($destination->roleFor($request->user()) === 'owner', 403);

        $project->update(['organization_id' => $destination->id]);

        app(AuditLogger::class)->log($organization, $request->user(), 'project.transferred', $project->name, [
            'destination_organization_id' => $destination->id,
        ]);
        app(AuditLogger::class)->log($destination, $request->user(), 'project.received', $project->name, [
            'source_organization_id' => $organization->id,
        ]);

        return redirect()->route('organizations.projects.show', [$destination, $project])
            ->with('status', "Project moved to {$destination->name}.");
    }

    /**
     * Owners/admins only. Wipes the project and everything under it
     * (issues, events, releases, notification channels all cascade).
     */
    public function destroy(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($request->input('confirm_name') !== "Bye bye {$project->name}") {
            return back()->withErrors(['confirm_name' => 'Type the confirmation phrase exactly to delete this project.']);
        }

        app(AuditLogger::class)->log($organization, $request->user(), 'project.deleted', $project->name);

        $project->delete();

        return redirect()->route('organizations.projects.index', $organization)->with('status', "\"{$project->name}\" has been deleted.");
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
}
