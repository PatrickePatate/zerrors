<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\Organization;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OrganizationAiSettings extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public Organization $organization;

    public string $aiProvider = '';

    public string $aiApiKey = '';

    public string $aiModel = '';

    public string $aiDeepModel = '';

    public bool $aiTriageEnabled = false;

    public string $aiTriageProvider = '';

    public string $aiTriageModel = '';

    public string $aiTriageApiKey = '';

    public bool $saved = false;

    public function mount(Organization $organization): void
    {
        $this->organization = $organization;
        $this->aiProvider = (string) $organization->ai_provider;
        $this->aiModel = (string) $organization->ai_model;
        $this->aiDeepModel = (string) $organization->ai_deep_model;
        $this->aiTriageEnabled = (bool) $organization->ai_triage_enabled;
        $this->aiTriageProvider = $organization->aiTriageProvider();
        $this->aiTriageModel = (string) $organization->aiTriageModelId();
    }

    public function save(): void
    {
        $this->authorizeManage($this->organization);
        $this->saved = false;

        $data = $this->validate([
            'aiProvider' => ['required', 'in:'.implode(',', array_keys(Organization::AI_PROVIDERS))],
            'aiApiKey' => ['nullable', 'string'],
            'aiModel' => ['nullable', 'string', 'max:255'],
            'aiDeepModel' => ['nullable', 'string', 'max:255'],
            'aiTriageEnabled' => ['boolean'],
            'aiTriageProvider' => ['required', 'in:'.implode(',', array_keys(Organization::AI_TRIAGE_PROVIDERS))],
            'aiTriageModel' => ['nullable', 'string', 'max:255'],
            'aiTriageApiKey' => ['nullable', 'string'],
        ]);

        $update = [
            'ai_provider' => $data['aiProvider'],
            'ai_model' => $data['aiModel'] ?: null,
            'ai_deep_model' => $data['aiDeepModel'] ?: null,
            'ai_triage_enabled' => $data['aiTriageEnabled'],
            'ai_triage_provider' => $data['aiTriageProvider'] !== config('fault.ai_triage_provider') ? $data['aiTriageProvider'] : null,
            'ai_triage_model' => ($data['aiTriageModel'] && $data['aiTriageModel'] !== config('fault.ai_triage_model')) ? $data['aiTriageModel'] : null,
        ];

        if (filled($data['aiTriageApiKey'])) {
            $update['ai_triage_api_key'] = $data['aiTriageApiKey'];
        }

        // Blank means "keep the existing key" — the field is never pre-filled with the real value.
        if (filled($data['aiApiKey'])) {
            $update['ai_api_key'] = $data['aiApiKey'];
        }

        $this->organization->update($update);
        $this->reset('aiApiKey', 'aiTriageApiKey');
        $this->saved = true;
    }

    public function disconnect(): void
    {
        $this->authorizeManage($this->organization);

        $this->organization->update(['ai_provider' => null, 'ai_api_key' => null, 'ai_model' => null, 'ai_deep_model' => null]);
        $this->reset('aiProvider', 'aiApiKey', 'aiModel', 'aiDeepModel');
    }

    public function render()
    {
        return view('livewire.organization-ai-settings');
    }
}
