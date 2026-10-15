<?php

use App\Actions\Reports\ExportReport;
use App\Enums\ReportFormat;
use App\Models\ReportGeneration;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * RF_00_4 · "Exportar" with the format picker (PDF or XLSX). Place it next to any report preview:
 *
 *     <livewire:reports.export-button report="assets_inventory" :filters="$filters" />
 *
 * It exports exactly the previewed filters, records the generation, confirms with a toast (RF_70, RF_71) and
 * downloads the file.
 */
new class extends Component {
    /** report_types.code */
    #[Locked]
    public string $report;

    /** @var array<string, mixed> */
    #[Reactive]
    public array $filters = [];

    public function export(string $format, ExportReport $exportReport): StreamedResponse
    {
        $this->authorize('create', ReportGeneration::class);
        Validator::make(['format' => $format], ['format' => ['required', Rule::enum(ReportFormat::class)]], [
            'format.required' => __('reports.export.invalid_format'),
            'format.enum' => __('reports.export.invalid_format'),
        ])->validate();

        $generation = $exportReport->handle(auth()->user(), $this->report, ReportFormat::from($format), $this->filters);

        Flux::toast(variant: 'success', text: __('reports.export.done'));

        $file = $generation->file;

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}; ?>

<div>
    <flux:error name="format" />
    @can('create', ReportGeneration::class)
        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" data-test="export-button">{{ __('reports.export.button') }}</flux:button>

            <flux:menu>
                @foreach (ReportFormat::cases() as $format)
                    <flux:menu.item wire:click="export('{{ $format->value }}')" :icon="$format === ReportFormat::Pdf ? 'document-text' : 'table-cells'">
                        {{ __('reports.export.'.$format->value) }}
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endcan
</div>
