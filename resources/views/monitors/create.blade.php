@extends('layouts.app')

@section('title', 'New monitor · '.$organization->name.' · Zerrors')

@section('content')
    <a href="{{ route('organizations.monitors.index', $organization) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> Monitoring
    </a>

    <x-card class="max-w-2xl">
        <h1 class="mb-4 text-lg font-semibold text-gray-900">New monitor</h1>

        <form method="POST" action="{{ route('organizations.monitors.store', $organization) }}" class="space-y-4"
              x-data="{ type: @js(old('type', \App\Enums\MonitorType::Http->value)) }"
              @change="if ($event.target.name === 'type') type = $event.target.value">
            @csrf

            <x-form.text-input label="Name" name="name" value="{{ old('name') }}" required :error="$errors->first('name')" />

            <x-form.select label="Type" id="type" name="type"
                            :options="collect(\App\Enums\MonitorType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])"
                            :selected="old('type', \App\Enums\MonitorType::Http->value)" />

            <x-form.text-input label="URL / host" name="url" value="{{ old('url') }}" required placeholder="https://example.com or example.com" :error="$errors->first('url')" />

            <x-form.select label="Project (optional)" id="project_id" name="project_id"
                            :options="$projects->pluck('name', 'id')->prepend('None', '')"
                            :selected="old('project_id', '')" />

            <div class="grid grid-cols-2 gap-4">
                <x-form.text-input label="Check interval (minutes)" name="check_interval_minutes" type="number" value="{{ old('check_interval_minutes', 5) }}" :error="$errors->first('check_interval_minutes')" />
                <x-form.text-input label="Timeout (seconds)" name="timeout_seconds" type="number" value="{{ old('timeout_seconds', 10) }}" :error="$errors->first('timeout_seconds')" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.select label="Request method" id="http_method" name="http_method"
                                :options="collect(\App\Enums\MonitorHttpMethod::cases())->mapWithKeys(fn ($method) => [$method->value => $method->label()])"
                                :selected="old('http_method', \App\Enums\MonitorHttpMethod::Get->value)" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.text-input label="Expected status code" name="expected_status_code" type="number" value="{{ old('expected_status_code', 200) }}" :error="$errors->first('expected_status_code')" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.textarea label="Request headers (optional)" name="headers_raw" rows="3"
                                  placeholder="Authorization: Bearer token&#10;X-Api-Key: secret"
                                  hint="One per line, as Name: Value. Sent with every check — use this for endpoints that require credentials."
                                  :error="$errors->first('headers_raw')">{{ old('headers_raw') }}</x-form.textarea>
            </div>

            <x-form.checkbox name="check_certificate" value="1" :checked="old('check_certificate')">
                Monitor SSL certificate expiration (HTTPS only)
            </x-form.checkbox>

            <x-form.checkbox name="is_active" value="1" checked>Active</x-form.checkbox>

            <div class="flex justify-end gap-2">
                <x-button tag="a" href="{{ route('organizations.monitors.index', $organization) }}" variant="secondary">Cancel</x-button>
                <x-button type="submit">Create monitor</x-button>
            </div>
        </form>
    </x-card>
@endsection
