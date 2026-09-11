<div>
    <x-table>
        <x-table.head>
            <x-table.column>Status</x-table.column>
            <x-table.column>Issue</x-table.column>
            <x-table.column>Project</x-table.column>
            <x-table.column>Level</x-table.column>
            <x-table.column>Events</x-table.column>
            <x-table.column>Last seen</x-table.column>
        </x-table.head>
        <x-table.body>
            @forelse($issues as $issue)
                <x-issue-row :issue="$issue" :organization="$organization" :members="$members" :show-project="true" />
            @empty
                <x-table.empty :colspan="6">No issues yet.</x-table.empty>
            @endforelse
        </x-table.body>
    </x-table>
</div>
