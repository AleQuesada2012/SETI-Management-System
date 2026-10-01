<?php

use App\Enums\PermissionName;
use App\Enums\ReportCategory;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Reports\ReportRegistry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('reports.index.title')] class extends Component
{
    #[Url(except: 'assets')]
    public string $category = 'assets';

    /** @return Collection<int, ReportType> */
    #[Computed]
    public function reports(): Collection
    {
        $this->authorize(PermissionName::ReportsView);
        abort_unless(ReportCategory::tryFrom($this->category) !== null, 404);
        $registry = app(ReportRegistry::class);

        return ReportType::query()->where('category', $this->category)->orderBy('sort_order')->get()
            ->filter(fn (ReportType $type): bool => auth()->user()->can($registry->permissionFor($type))
                && ($registry->has($type->code) || $registry->isUpcoming($type)));
    }

    #[Computed]
    public function hasAvailableReports(): bool
    {
        return $this->reports->contains(fn (ReportType $type): bool => app(ReportRegistry::class)->has($type->code));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading level="2">{{ __('reports.index.title') }}</flux:heading>
        @can('viewAny', ReportGeneration::class)
            <flux:button icon="clock" :href="route('reports.generations')" wire:navigate>{{ __('reports.index.generations') }}</flux:button>
        @endcan
    </div>

    <flux:select wire:model.live="category" :label="__('reports.index.category')" class="md:max-w-sm">
        @foreach (ReportCategory::cases() as $item)
            <flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    @if (! $this->hasAvailableReports)
        <flux:callout icon="information-circle" :heading="__('reports.index.empty')" />
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($this->reports as $type)
            <flux:card wire:key="report-{{ $type->id }}" class="flex flex-col items-start gap-3">
                <flux:heading>{{ $type->name }}</flux:heading>
                @if (app(ReportRegistry::class)->has($type->code))
                    <flux:button :href="route('reports.show', $type->code)" wire:navigate>{{ __('reports.index.open') }}</flux:button>
                @else
                    <flux:badge>{{ __('reports.index.upcoming') }}</flux:badge>
                @endif
            </flux:card>
        @endforeach
    </div>
</div>
