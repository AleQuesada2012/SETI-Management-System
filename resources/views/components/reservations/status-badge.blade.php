{{--
    The state of a reservation as users read it (ADR-0005): the derived effective status and, when it was changed
    after it was made, the "Modificada" label.

    <x-reservations.status-badge :status="$row->effective_status" :modified="$row->was_modified" />
--}}
@props([
    'status',
    'modified' => false,
])

@php
    $color = match ($status) {
        App\Enums\ReservationEffectiveStatus::Upcoming => 'green',
        App\Enums\ReservationEffectiveStatus::InProgress => 'amber',
        App\Enums\ReservationEffectiveStatus::Finished => 'zinc',
        App\Enums\ReservationEffectiveStatus::Cancelled => 'red',
    };
@endphp

<span {{ $attributes->class('inline-flex flex-wrap gap-1') }}>
    <flux:badge size="sm" :color="$color">{{ $status->label() }}</flux:badge>
    @if ($modified)
        <flux:badge size="sm" color="zinc">{{ __('reservations.status.modified') }}</flux:badge>
    @endif
</span>
