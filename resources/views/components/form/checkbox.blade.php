@props(['checked' => false])

<label class="flex items-center gap-2 text-sm text-gray-700">
    {{--
        An unchecked checkbox simply isn't included in the submitted form data,
        so a plain `name`d checkbox (not Livewire's wire:model, which tracks
        state itself over the wire) needs this hidden "0" fallback in front of
        it — otherwise unchecking has nothing to tell the server about, and a
        controller default ends up silently overriding the user's choice.
    --}}
    @if($attributes->has('name'))
        <input type="hidden" name="{{ $attributes->get('name') }}" value="0">
    @endif
    <input type="checkbox" @if($checked) checked @endif {{ $attributes->class('h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900/10') }}>
    {{ $slot }}
</label>
