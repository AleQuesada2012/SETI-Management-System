<?php

use App\Enums\PermissionName;
use App\Reports\PowerBiFeed;
use Illuminate\Support\Carbon;
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

    /**
     * When Power BI last read the feed (RF_73): `fresh`, `stale` past `reports.powerbi_feed.stale_after_hours`,
     * or `never`. Null when the feed is off, since then the report is refreshed some other way.
     *
     * @return array{state: string, at: ?Carbon}|null
     */
    #[Computed]
    public function dataStatus(): ?array
    {
        $this->authorize(PermissionName::DashboardsPowerbi);

        if (! config('reports.powerbi_feed.password')) {
            return null;
        }

        $at = PowerBiFeed::lastPulledAt();
        $state = match (true) {
            $at === null => 'never',
            $at->lt(now()->subHours(config('reports.powerbi_feed.stale_after_hours'))) => 'stale',
            default => 'fresh',
        };

        return ['state' => $state, 'at' => $at];
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
        @if ($this->dataStatus && $this->dataStatus['state'] !== 'fresh')
            <flux:callout
                variant="warning"
                icon="exclamation-triangle"
                :text="$this->dataStatus['state'] === 'stale'
                    ? __('reports.powerbi.stale', ['date' => $this->dataStatus['at']->format('d/m/Y H:i')])
                    : __('reports.powerbi.never_updated')"
            />
        @endif
        <div class="flex flex-col gap-2">
            <flux:text>
                {{ __('reports.powerbi.refresh') }}
                @if ($this->dataStatus && $this->dataStatus['state'] === 'fresh')
                    {{ __('reports.powerbi.last_updated', ['date' => $this->dataStatus['at']->format('d/m/Y H:i')]) }}
                @endif
            </flux:text>
            <flux:text>
                {{ __('reports.powerbi.sign_in_help') }}
                <flux:link :href="$this->embedUrl" target="_blank" rel="noopener">{{ __('reports.powerbi.open_direct') }}</flux:link>
            </flux:text>
        </div>
    @else
        <flux:callout icon="information-circle" :text="__('reports.powerbi.not_configured')" />
    @endif
</div>
