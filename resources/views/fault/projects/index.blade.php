@extends('layouts.app')

@section('title', $organization->name.' · Zerrors')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-900">Projects</h1>

        <x-modal :open-on-error="$errors->has('name') || $errors->has('platform') || request()->boolean('new')">
            <x-slot:trigger>
                <x-button type="button">
                    <x-lucide-plus class="h-4 w-4" />
                    New project
                </x-button>
            </x-slot:trigger>

            <h2 class="mb-4 text-sm font-medium text-gray-700">New project</h2>
            <form method="POST" action="{{ route('organizations.projects.store', $organization) }}" class="space-y-4">
                @csrf
                <x-form.text-input label="Name" name="name" placeholder="Project name" value="{{ old('name') }}" :error="$errors->first('name')" required autofocus />
                <x-form.select label="Type" id="platform" name="platform"
                                :options="collect(\App\Enums\FaultPlatform::cases())->mapWithKeys(fn ($platform) => [$platform->value => $platform->label()])"
                                :selected="old('platform')" />
                <div class="flex justify-end gap-2">
                    <x-button type="button" variant="secondary" @click="open = false">Cancel</x-button>
                    <x-button type="submit">Create</x-button>
                </div>
            </form>
        </x-modal>
    </div>

    <x-card :padding="false">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-600">
                    <th class="px-5 py-3 font-medium">Project</th>
                    <th class="px-5 py-3 font-medium">Issues</th>
                    <th class="px-5 py-3 font-medium">Unresolved</th>
                    <th class="px-5 py-3 font-medium">DSN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($projects as $project)
                    <tr class="hover:bg-gray-50">
                        <td class="flex items-center gap-2 px-5 py-3">
                            <x-tooltip :message="$project->platform->label()">{{ $project->platform->icon(size: '6') }}</x-tooltip>
                            <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="font-semibold text-gray-900 hover:underline">{{ $project->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $project->issues_count }}</td>
                        <td class="px-5 py-3">
                            @if($project->unresolved_issues_count > 0)
                                <x-badge color="red">{{ $project->unresolved_issues_count }}</x-badge>
                            @else
                                <span class="text-gray-400">0</span>
                            @endif
                        </td>
                        <td class="px-5 py-3"><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $project->dsn() }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-sm text-gray-400">No projects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
@endsection
