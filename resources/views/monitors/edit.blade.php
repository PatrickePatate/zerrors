@extends('layouts.app')

@section('title', 'Edit '.$monitor->name.' · '.$organization->name.' · Zerrors')

@section('content')
    <a href="{{ route('organizations.monitors.show', [$organization, $monitor]) }}" class="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900">
        <x-lucide-arrow-left class="h-4 w-4" /> {{ $monitor->name }}
    </a>

    <x-card class="max-w-2xl">
        <h1 class="mb-4 text-lg font-semibold text-gray-900">Edit monitor</h1>

        <form method="POST" action="{{ route('organizations.monitors.update', [$organization, $monitor]) }}" class="space-y-4"
              x-data="{ type: @js(old('type', $monitor->type->value)) }"
              @change="if ($event.target.name === 'type') type = $event.target.value">
            @csrf
            @method('PATCH')

            <x-form.text-input label="Name" name="name" value="{{ old('name', $monitor->name) }}" required :error="$errors->first('name')" />

            <x-form.select label="Type" id="type" name="type"
                            :options="collect(\App\Enums\MonitorType::cases())->mapWithKeys(fn ($type) => [$type->value => $type->label()])"
                            :selected="old('type', $monitor->type->value)" />

            <x-form.text-input label="URL / host" name="url" value="{{ old('url', $monitor->url) }}" required :error="$errors->first('url')" />

            <x-form.select label="Project (optional)" id="project_id" name="project_id"
                            :options="$projects->pluck('name', 'id')->prepend('None', '')"
                            :selected="old('project_id', $monitor->project_id ?? '')" />

            <div class="grid grid-cols-2 gap-4">
                <x-form.text-input label="Check interval (minutes)" name="check_interval_minutes" type="number" value="{{ old('check_interval_minutes', $monitor->check_interval_minutes) }}" :error="$errors->first('check_interval_minutes')" />
                <x-form.text-input label="Timeout (seconds)" name="timeout_seconds" type="number" value="{{ old('timeout_seconds', $monitor->timeout_seconds) }}" :error="$errors->first('timeout_seconds')" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.select label="Request method" id="http_method" name="http_method"
                                :options="collect(\App\Enums\MonitorHttpMethod::cases())->mapWithKeys(fn ($method) => [$method->value => $method->label()])"
                                :selected="old('http_method', $monitor->http_method->value)" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.text-input label="Expected status code" name="expected_status_code" type="number" value="{{ old('expected_status_code', $monitor->expected_status_code) }}" :error="$errors->first('expected_status_code')" />
            </div>

            <div x-show="type !== 'ping'" x-cloak>
                <x-form.textarea label="Request headers (optional)" name="headers_raw" rows="3"
                                  placeholder="Authorization: Bearer token&#10;X-Api-Key: secret"
                                  hint="One per line, as Name: Value. Sent with every check — use this for endpoints that require credentials."
                                  :error="$errors->first('headers_raw')">{{ old('headers_raw', collect($monitor->headers ?? [])->map(fn ($value, $name) => "{$name}: {$value}")->implode("\n")) }}</x-form.textarea>
            </div>

            <x-form.checkbox name="check_certificate" value="1" :checked="old('check_certificate', $monitor->check_certificate)">
                Monitor SSL certificate expiration (HTTPS only)
            </x-form.checkbox>

            <x-form.checkbox name="is_active" value="1" :checked="old('is_active', $monitor->is_active)">Active</x-form.checkbox>

            <div class="flex justify-end gap-2">
                <x-button tag="a" href="{{ route('organizations.monitors.show', [$organization, $monitor]) }}" variant="secondary">Cancel</x-button>
                <x-button type="submit">Save changes</x-button>
            </div>
        </form>
    </x-card>
@endsection
