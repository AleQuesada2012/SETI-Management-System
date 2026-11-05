@props(['series', 'label', 'key', 'type' => 'doughnut'])

<div wire:key="{{ $key }}" x-data="setiChart(@js($series), @js($type))" class="relative h-64 text-ink" wire:ignore>
    <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
</div>
