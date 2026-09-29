{{--
    Temporary content of a page whose requirements are still open. Whoever implements them replaces it.

    <x-module-placeholder owner="David Zamora (@DavidZamoraB)" :requirements="['RF_07' => 'Búsqueda de activos']">
        Optional notes: which Query, Policy or component to use.
    </x-module-placeholder>
--}}
@props([
    'owner',
    'requirements' => [],
])

<flux:callout icon="wrench-screwdriver" color="zinc" {{ $attributes }}>
    <flux:callout.heading>{{ __('app.placeholder.heading') }}</flux:callout.heading>

    <flux:callout.text>
        <p>{{ __('app.placeholder.owner', ['owner' => $owner]) }}</p>

        <ul class="mt-2 list-disc space-y-0.5 ps-5">
            @foreach ($requirements as $code => $requirement)
                <li><span class="font-mono text-xs">{{ $code }}</span> · {{ $requirement }}</li>
            @endforeach
        </ul>

        @if ($slot->isNotEmpty())
            <div class="mt-2">{{ $slot }}</div>
        @endif
    </flux:callout.text>
</flux:callout>
