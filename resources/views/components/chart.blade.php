@props(['series', 'label', 'key'])

<div wire:key="{{ $key }}" x-data="setiChart(@js($series))" class="relative h-64 text-ink" wire:ignore>
    <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
</div>
