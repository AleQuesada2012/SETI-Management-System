@props(['series', 'label', 'key', 'type' => 'doughnut'])

{{-- The rows travel in data-series: Blade turns an array passed to the js directive into JSON.parse(…), which Livewire's CSP-safe build cannot run. --}}
<div wire:key="{{ $key }}" x-data="setiChart(@js($type))" data-series="{{ json_encode($series) }}" class="relative h-64 text-ink" wire:ignore>
    <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
</div>
