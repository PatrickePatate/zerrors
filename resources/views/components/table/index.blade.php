@props(['bordered' => true])

<div {{ $attributes->class([
    'overflow-hidden rounded-lg border border-gray-200 bg-white' => $bordered,
]) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            {{ $slot }}
        </table>
    </div>
</div>
