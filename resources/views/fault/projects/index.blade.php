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

    <x-table>
        <x-table.head>
            <x-table.column>Project</x-table.column>
            <x-table.column>Issues</x-table.column>
            <x-table.column>Unresolved</x-table.column>
            <x-table.column>DSN</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($projects as $project)
                <x-table.row>
                    <x-table.cell class="flex items-center gap-2">
                        <x-tooltip :message="$project->platform->label()">{{ $project->platform->icon(size: '6') }}</x-tooltip>
                        <a href="{{ route('organizations.projects.show', [$organization, $project]) }}" class="font-semibold text-gray-900 hover:underline">{{ $project->name }}</a>
                    </x-table.cell>
                    <x-table.cell class="text-gray-600">{{ $project->issues_count }}</x-table.cell>
                    <x-table.cell>
                        @if($project->unresolved_issues_count > 0)
                            <x-badge color="red">{{ $project->unresolved_issues_count }}</x-badge>
                        @else
                            <span class="text-gray-400">0</span>
                        @endif
                    </x-table.cell>
                    <x-table.cell><code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $project->dsn() }}</code></x-table.cell>
                </x-table.row>
            @empty
                <x-table.empty :colspan="4">No projects yet.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>
@endsection
