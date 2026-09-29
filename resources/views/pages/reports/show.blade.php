<?php

use App\Models\ReportType;
use App\Reports\ReportDefinition;
use App\Reports\ReportRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * One report: preview with the current filters, then export (RF_00_4, RF_63, RF_64…). Generic: it renders any
 * ReportDefinition registered in ReportRegistry for report_types.code = {report}.
 */
new class extends Component {
    public ReportType $report;

    /** @var array<string, mixed> Filters of the preview; the export uses the same ones (RF_62). */
    #[Url(except: [])]
    public array $filters = [];

    public const PREVIEW_ROWS = 50;

    public function mount(ReportRegistry $registry): void
    {
        if ($registry->has($this->report->code)) {
            $this->authorize($registry->get($this->report->code)->permission());
        }
    }

    #[Computed]
    public function definition(): ?ReportDefinition
    {
        $registry = app(ReportRegistry::class);

        return $registry->has($this->report->code) ? $registry->get($this->report->code) : null;
    }

    #[Computed]
    public function preview(): LengthAwarePaginator
    {
        $filters = $this->definition->normalizeFilters($this->filters);

        return $this->definition->query($filters)->paginate(self::PREVIEW_ROWS);
    }

    public function render()
    {
        return $this->view()->title($this->report->name);
    }
}; ?>

<div class="flex flex-col gap-6">
    @if ($this->definition === null)
        <flux:callout icon="information-circle" color="zinc" :heading="__('reports.show.unavailable')" />
    @else
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading level="2">{{ __('reports.show.preview') }}</flux:heading>
            <livewire:reports.export-button :report="$report->code" :filters="$this->definition->normalizeFilters($filters)" />
        </div>

        <flux:card class="overflow-x-auto">
            @if ($this->preview->isEmpty())
                <flux:text>{{ __('reports.document.empty') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        @foreach ($this->definition->columns() as $header)
                            <flux:table.column>{{ $header }}</flux:table.column>
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->preview as $row)
                            <flux:table.row>
                                @foreach ($this->definition->cells($row) as $cell)
                                    <flux:table.cell>{{ $cell }}</flux:table.cell>
                                @endforeach
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                <flux:text size="sm" class="mt-3">
                    @if ($this->preview->total() > $this->preview->count())
                        {{ __('reports.show.preview_limit', ['shown' => $this->preview->count(), 'total' => $this->preview->total()]) }}
                    @else
                        {{ __('reports.show.total', ['total' => $this->preview->total()]) }}
                    @endif
                </flux:text>
            @endif
        </flux:card>
    @endif
</div>
