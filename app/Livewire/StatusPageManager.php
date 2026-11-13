<?php

namespace App\Livewire;

use App\Enums\StatusIncidentState;
use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\Organization;
use App\Models\StatusIncident;
use App\Models\StatusPage;
use App\Support\Organization\AuditLogger;
use App\Support\Status\StatusPageData;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StatusPageManager extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public Organization $organization;

    public string $slug = '';

    public string $title = '';

    public string $description = '';

    public bool $isPublic = false;

    /** @var list<array{id: int, original: string, name: string, selected: bool}> */
    public array $monitorRows = [];

    public string $incidentTitle = '';

    public string $incidentBody = '';

    public string $incidentState = 'investigating';

    /** @var array<int, string> */
    public array $updateBodies = [];

    /** @var array<int, string> */
    public array $updateStates = [];

    public bool $saved = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->authorizeManage($this->organization);

        $page = $organization->statusPage;
        $this->slug = $page->slug ?? Str::slug($organization->slug);
        $this->title = $page->title ?? $organization->name.' Status';
        $this->description = (string) ($page->description ?? '');
        $this->isPublic = (bool) ($page->is_public ?? false);

        $selected = $page ? $page->monitors()->get()->keyBy('id') : collect();
        $rows = $selected->map(fn ($monitor): array => [
            'id' => $monitor->id,
            'original' => $monitor->name,
            'name' => (string) ($monitor->pivot->display_name ?: $monitor->name),
            'selected' => true,
        ])->values();

        $others = $organization->monitors()->whereNotIn('id', $selected->keys())->orderBy('name')->get()
            ->map(fn ($monitor): array => ['id' => $monitor->id, 'original' => $monitor->name, 'name' => $monitor->name, 'selected' => false]);

        $this->monitorRows = $rows->concat($others)->all();
    }

    public function moveUp(int $index): void
    {
        $this->swap($index, $index - 1);
    }

    public function moveDown(int $index): void
    {
        $this->swap($index, $index + 1);
    }

    protected function swap(int $from, int $to): void
    {
        $this->authorizeManage($this->organization);

        if (! isset($this->monitorRows[$from], $this->monitorRows[$to])) {
            return;
        }

        [$this->monitorRows[$from], $this->monitorRows[$to]] = [$this->monitorRows[$to], $this->monitorRows[$from]];
    }

    public function save(): void
    {
        $this->authorizeManage($this->organization);
        $this->saved = false;

        $this->slug = Str::slug($this->slug);
        $existing = $this->organization->statusPage;

        $this->validate([
            'slug' => ['required', 'string', 'max:60', Rule::unique('status_pages', 'slug')->ignore($existing?->id)],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'monitorRows.*.name' => ['nullable', 'string', 'max:120'],
        ]);

        $page = StatusPage::updateOrCreate(
            ['organization_id' => $this->organization->id],
            [
                'slug' => $this->slug,
                'title' => $this->title,
                'description' => $this->description !== '' ? $this->description : null,
                'is_public' => $this->isPublic,
            ],
        );

        $allowedIds = $this->organization->monitors()->pluck('id')->all();
        $sync = [];
        $position = 0;

        foreach ($this->monitorRows as $row) {
            if ($row['selected'] && in_array($row['id'], $allowedIds, true)) {
                $sync[$row['id']] = ['display_name' => trim($row['name']) !== '' ? trim($row['name']) : null, 'position' => $position++];
            }
        }

        $page->monitors()->sync($sync);
        StatusPageData::forget($page);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'status_page.updated', $page->slug, [
            'is_public' => $page->is_public,
            'monitors' => count($sync),
        ]);

        $this->saved = true;
    }

    public function createIncident(): void
    {
        $this->authorizeManage($this->organization);
        $page = $this->organization->statusPage;
        abort_if($page === null, 422, 'Save the status page first.');

        $this->validate([
            'incidentTitle' => ['required', 'string', 'max:160'],
            'incidentBody' => ['required', 'string', 'max:2000'],
            'incidentState' => ['required', Rule::enum(StatusIncidentState::class)],
        ]);

        $state = StatusIncidentState::from($this->incidentState);

        $incident = $page->incidents()->create([
            'title' => $this->incidentTitle,
            'started_at' => now(),
            'resolved_at' => $state === StatusIncidentState::Resolved ? now() : null,
            'auto' => false,
        ]);
        $incident->updates()->create(['status' => $state, 'body' => $this->incidentBody]);

        StatusPageData::forget($page);
        app(AuditLogger::class)->log($this->organization, auth()->user(), 'status_incident.created', $incident->title);

        $this->reset('incidentTitle', 'incidentBody');
        $this->incidentState = 'investigating';
    }

    public function postUpdate(int $incidentId): void
    {
        $this->authorizeManage($this->organization);
        $incident = $this->findIncident($incidentId);
        $this->updateStates[$incidentId] ??= StatusIncidentState::Investigating->value;

        $this->validate([
            "updateBodies.{$incidentId}" => ['required', 'string', 'max:2000'],
            "updateStates.{$incidentId}" => ['required', Rule::enum(StatusIncidentState::class)],
        ]);

        $state = StatusIncidentState::from($this->updateStates[$incidentId]);

        $incident->updates()->create(['status' => $state, 'body' => $this->updateBodies[$incidentId]]);
        $incident->update(['resolved_at' => $state === StatusIncidentState::Resolved ? ($incident->resolved_at ?? now()) : null]);

        StatusPageData::forget($incident->statusPage);
        app(AuditLogger::class)->log($this->organization, auth()->user(), 'status_incident.updated', $incident->title, ['status' => $state->value]);

        unset($this->updateBodies[$incidentId], $this->updateStates[$incidentId]);
    }

    public function resolveIncident(int $incidentId): void
    {
        $this->authorizeManage($this->organization);
        $incident = $this->findIncident($incidentId);

        $incident->update(['resolved_at' => now()]);
        $incident->updates()->create(['status' => StatusIncidentState::Resolved, 'body' => 'This incident has been resolved.']);

        StatusPageData::forget($incident->statusPage);
        app(AuditLogger::class)->log($this->organization, auth()->user(), 'status_incident.resolved', $incident->title);
    }

    protected function findIncident(int $incidentId): StatusIncident
    {
        $page = $this->organization->statusPage;
        abort_if($page === null, 404);

        return $page->incidents()->findOrFail($incidentId);
    }

    public function render()
    {
        $page = $this->organization->statusPage;

        return view('livewire.status-page-manager', [
            'page' => $page,
            'incidents' => $page ? $page->incidents()->with('updates')->latest('started_at')->limit(20)->get() : collect(),
            'states' => StatusIncidentState::cases(),
        ]);
    }
}
