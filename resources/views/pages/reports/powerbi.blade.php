<?php

use App\Enums\PermissionName;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('reports.powerbi.title')] class extends Component
{
    public function mount(): void
    {
        $this->authorize(PermissionName::DashboardsPowerbi);
    }

    /** The secure-embed URL (RF_61); viewers sign in with their licensed Microsoft account inside the frame. */
    #[Computed]
    public function embedUrl(): ?string
    {
        $this->authorize(PermissionName::DashboardsPowerbi);

        return config('reports.powerbi_embed_url') ?: null;
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading level="2" size="xl">{{ __('reports.powerbi.title') }}</flux:heading>
        <flux:text class="mt-2">{{ __('reports.powerbi.intro') }}</flux:text>
    </div>

    @if ($this->embedUrl)
        <iframe
            src="{{ $this->embedUrl }}"
            title="{{ __('reports.powerbi.title') }}"
            allowfullscreen
            class="h-[75vh] w-full rounded-lg border border-line bg-surface"
        ></iframe>
        <flux:text>{{ __('reports.powerbi.refresh') }}</flux:text>
    @else
        <flux:callout icon="information-circle" :text="__('reports.powerbi.not_configured')" />
    @endif
</div>
