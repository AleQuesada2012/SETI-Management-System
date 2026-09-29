@props([
    'sidebar' => false,
])

{{-- Blue square + app name, as in the mockups ("Vistas de la aplicación"). --}}
@if ($sidebar)
    <flux:sidebar.brand :name="config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-brand text-brand-foreground">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name')" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-brand text-brand-foreground">
            <x-app-logo-icon class="size-5" />
        </x-slot>
    </flux:brand>
@endif
