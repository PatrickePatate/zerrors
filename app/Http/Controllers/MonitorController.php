<?php

namespace App\Http\Controllers;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Organization;
use App\Support\Net\OutboundUrlGuard;
use App\Support\Organization\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class MonitorController extends Controller
{
    public function index(Request $request, Organization $organization): View
    {
        Gate::authorize('view', $organization);

        $monitors = $organization->monitors()
            ->with('project')
            ->when($request->filled('project'), fn ($q) => $q->whereHas(
                'project',
                fn ($q) => $q->where('slug', $request->query('project'))
            ))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('monitors.index', [
            'organization' => $organization,
            'monitors' => $monitors,
            'projectFilter' => $request->query('project'),
        ]);
    }

    public function create(Request $request, Organization $organization): View
    {
        Gate::authorize('manage', $organization);

        return view('monitors.create', [
            'organization' => $organization,
            'projects' => $organization->projects()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization)
    {
        Gate::authorize('manage', $organization);

        $data = $this->validateData($request, $organization);

        $monitor = $organization->monitors()->create($data);

        app(AuditLogger::class)->log($organization, $request->user(), 'monitor.created', $monitor->name, [
            'type' => $monitor->type->value,
            'url' => $monitor->url,
        ]);

        return redirect()->route('organizations.monitors.show', [$organization, $monitor])
            ->with('status', "Monitor \"{$monitor->name}\" created.");
    }

    public function show(Request $request, Organization $organization, Monitor $monitor): View
    {
        abort_unless($monitor->organization_id === $organization->id, 404);
        Gate::authorize('view', $organization);

        return view('monitors.show', [
            'organization' => $organization,
            'monitor' => $monitor,
        ]);
    }

    public function edit(Request $request, Organization $organization, Monitor $monitor): View
    {
        abort_unless($monitor->organization_id === $organization->id, 404);
        Gate::authorize('manage', $organization);

        return view('monitors.edit', [
            'organization' => $organization,
            'monitor' => $monitor,
            'projects' => $organization->projects()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Organization $organization, Monitor $monitor)
    {
        abort_unless($monitor->organization_id === $organization->id, 404);
        Gate::authorize('manage', $organization);

        $data = $this->validateData($request, $organization);

        $monitor->update($data);

        app(AuditLogger::class)->log($organization, $request->user(), 'monitor.updated', $monitor->name);

        return redirect()->route('organizations.monitors.show', [$organization, $monitor])
            ->with('status', "Monitor \"{$monitor->name}\" updated.");
    }

    public function destroy(Request $request, Organization $organization, Monitor $monitor)
    {
        abort_unless($monitor->organization_id === $organization->id, 404);
        Gate::authorize('manage', $organization);

        $request->validate([
            'confirm_name' => ['required', 'string'],
        ]);

        if ($request->input('confirm_name') !== "Bye bye {$monitor->name}") {
            return back()->withErrors(['confirm_name' => 'Type the confirmation phrase exactly to delete this monitor.']);
        }

        $monitorName = $monitor->name;

        $monitor->delete();

        app(AuditLogger::class)->log($organization, $request->user(), 'monitor.deleted', $monitorName);

        return redirect()->route('organizations.monitors.index', $organization)
            ->with('status', "Monitor \"{$monitorName}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateData(Request $request, Organization $organization): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', Rule::exists('fault_projects', 'id')->where('organization_id', $organization->id)],
            'type' => ['required', Rule::enum(MonitorType::class)],
            'url' => ['required', 'string', 'max:2048', $this->outboundTargetRule($request)],
            'http_method' => ['nullable', Rule::enum(MonitorHttpMethod::class)],
            'check_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'expected_status_code' => ['nullable', 'integer', 'min:100', 'max:599'],
            'headers_raw' => ['nullable', 'string', 'max:4000'],
            'check_certificate' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $type = MonitorType::from($data['type']);

        // Ping has no HTTP request/response involved at all — expected status,
        // method, and headers are all HTTP(S)/Laravel Health-only.
        $data['expected_status_code'] = $type === MonitorType::Ping ? null : ($data['expected_status_code'] ?? 200);
        $data['http_method'] = $type === MonitorType::Ping
            ? MonitorHttpMethod::Get->value
            : ($data['http_method'] ?? MonitorHttpMethod::Get->value);
        $data['headers'] = $type === MonitorType::Ping ? null : $this->parseHeaders((string) $request->input('headers_raw', ''));
        unset($data['headers_raw']);

        $data['check_certificate'] = $request->boolean('check_certificate');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    /**
     * Rejects targets the server must not connect to (loopback, LAN, cloud
     * metadata, ...). Ping monitors store a bare host; the others store a URL.
     */
    protected function outboundTargetRule(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            if (! is_string($value)) {
                return;
            }

            $guard = app(OutboundUrlGuard::class);

            try {
                if (MonitorType::tryFrom((string) $request->input('type')) === MonitorType::Ping) {
                    $guard->assertAllowedHost($value);
                } else {
                    $guard->assertAllowedUrl($value);
                }
            } catch (InvalidArgumentException $e) {
                $fail($e->getMessage());
            }
        };
    }

    /**
     * Parses "Name: Value" lines (one header per line) into an associative
     * array, ignoring blank lines and lines missing the colon separator.
     *
     * @return array<string, string>|null
     */
    protected function parseHeaders(string $raw): ?array
    {
        $headers = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);

            if ($name !== '') {
                $headers[$name] = trim($value);
            }
        }

        return $headers === [] ? null : $headers;
    }
}
