<?php

namespace App\Livewire;

use App\Models\Organization;
use Livewire\Component;

class OrganizationAiSettings extends Component
{
    public Organization $organization;

    public string $aiProvider = '';

    public string $aiApiKey = '';

    public string $aiModel = '';

    public bool $saved = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->aiProvider = (string) $organization->ai_provider;
        $this->aiModel = (string) $organization->ai_model;
    }

    protected function authorizeManage(): void
    {
        abort_unless(in_array($this->organization->roleFor(auth()->user()), ['owner', 'admin'], true), 403);
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->saved = false;

        $data = $this->validate([
            'aiProvider' => ['required', 'in:'.implode(',', array_keys(Organization::AI_PROVIDERS))],
            'aiApiKey' => ['nullable', 'string'],
            'aiModel' => ['nullable', 'string', 'max:255'],
        ]);

        $update = [
            'ai_provider' => $data['aiProvider'],
            'ai_model' => $data['aiModel'] ?: null,
        ];

        // Blank means "keep the existing key" — the field is never pre-filled with the real value.
        if (filled($data['aiApiKey'])) {
            $update['ai_api_key'] = $data['aiApiKey'];
        }

        $this->organization->update($update);
        $this->reset('aiApiKey');
        $this->saved = true;
    }

    public function disconnect(): void
    {
        $this->authorizeManage();

        $this->organization->update(['ai_provider' => null, 'ai_api_key' => null, 'ai_model' => null]);
        $this->reset('aiProvider', 'aiApiKey', 'aiModel');
    }

    public function render()
    {
        return view('livewire.organization-ai-settings');
    }
}
