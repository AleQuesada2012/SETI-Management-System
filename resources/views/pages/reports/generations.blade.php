<?php

use App\Models\ReportGeneration;
use App\Queries\Reports\ReportHistory;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('reports.generations.title')] class extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('viewAny', ReportGeneration::class);
    }

    public function hydrate(): void
    {
        $this->authorize('viewAny', ReportGeneration::class);
    }

    #[Computed]
    public function generations(): LengthAwarePaginator
    {
        $this->authorize('viewAny', ReportGeneration::class);
        return ReportHistory::query()->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:button icon="arrow-left" :href="route('reports.index')" wire:navigate class="self-start">{{ __('reports.show.back') }}</flux:button>
    <div>
        <flux:heading level="2">{{ __('reports.generations.title') }}</flux:heading>
        <flux:text class="mt-2">{{ __('reports.generations.description') }}</flux:text>
    </div>
    @if ($this->generations->isEmpty())
        <flux:callout icon="information-circle" :heading="__('reports.generations.empty')" />
    @else
        <flux:card class="min-w-0 overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    @foreach (['when', 'who', 'report', 'category', 'format', 'rows', 'file'] as $column)
                        <flux:table.column>{{ __('reports.generations.'.$column) }}</flux:table.column>
                    @endforeach
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->generations as $generation)
                        <flux:table.row :key="$generation->id">
                            <flux:table.cell class="whitespace-nowrap">{{ $generation->generated_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell>{{ $generation->generated_by_name }}</flux:table.cell>
                            <flux:table.cell>{{ $generation->report_name }}</flux:table.cell>
                            <flux:table.cell>{{ $generation->report_category->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $generation->format->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $generation->row_count }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($generation->file_available)
                                    <flux:button size="sm" icon="arrow-down-tray" :href="route('reports.generations.download', $generation->id)">{{ __('reports.generations.download') }}</flux:button>
                                @else
                                    {{ __('reports.generations.unavailable') }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
        {{ $this->generations->links() }}
    @endif
</div>
