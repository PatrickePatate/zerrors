@props(['colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-5 py-6 text-sm text-gray-400">
        {{ $slot }}
    </td>
</tr>
