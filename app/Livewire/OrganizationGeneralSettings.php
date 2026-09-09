<?php

namespace App\Livewire;

use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Livewire\Component;

class OrganizationGeneralSettings extends Component
{
    public Organization $organization;

    public string $name = '';

    public bool $alertsEnabled = false;

    public bool $require2fa = false;

    public bool $saved = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->name = $organization->name;
        $this->alertsEnabled = (bool) $organization->alerts_enabled;
        $this->require2fa = (bool) $organization->require_2fa;
    }

    public function save(): void
    {
        abort_unless(in_array($this->organization->roleFor(auth()->user()), ['owner', 'admin'], true), 403);

        $this->saved = false;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $this->organization->update([
            'name' => $data['name'],
            'alerts_enabled' => $this->alertsEnabled,
            'require_2fa' => $this->require2fa,
        ]);

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'organization.updated');

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.organization-general-settings');
    }
}
