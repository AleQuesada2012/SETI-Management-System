<?php

namespace App\Actions\Reports;

use App\Enums\AuditAction;
use App\Enums\ReportFormat;
use App\Models\File;
use App\Models\ReportGeneration;
use App\Models\ReportType;
use App\Models\User;
use App\Reports\Renderers\PdfRenderer;
use App\Reports\Renderers\ReportRenderer;
use App\Reports\Renderers\XlsxRenderer;
use App\Reports\ReportDocument;
use App\Reports\ReportRegistry;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * RF_00_4 · Export a report in the format the user picked (RF_70 PDF, RF_71 XLSX). In one transaction it stores
 * the file, records the generation with its filters, format, author and time (report_generations, RF_84) and writes
 * the `generated` audit entry. If anything fails, nothing is recorded and the stored file is removed.
 */
final class ExportReport
{
    public function __construct(private readonly ReportRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $filters  the filters of the preview; unknown keys and empty values are dropped
     */
    public function handle(User $user, string $reportCode, ReportFormat $format, array $filters = []): ReportGeneration
    {
        $definition = $this->registry->get($reportCode);

        Gate::forUser($user)->authorize('create', ReportGeneration::class);
        Gate::forUser($user)->authorize($definition->permission()->value);

        $type = ReportType::query()->where('code', $reportCode)->firstOrFail();
        $filters = $definition->normalizeFilters($filters);
        $rows = $definition->query($filters)->get();
        $generatedAt = now();

        $document = new ReportDocument(
            title: $type->name,
            generatedAt: $generatedAt,
            generatedBy: $user->name,
            filters: $definition->describeFilters($filters),
            headers: array_values($definition->columns()),
            rows: $rows->map(fn ($row) => $definition->cells($row))->values()->all(),
            landscape: $definition->landscape(),
        );

        $contents = $this->renderer($format)->render($document);

        $disk = (string) config('filesystems.files_disk');
        $path = sprintf('reports/%s/%s-%s.%s', $generatedAt->format('Y/m'), $reportCode, Str::uuid7(), $format->extension());

        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException("Could not store the exported report on disk [{$disk}].");
        }

        try {
            return DB::transaction(function () use ($user, $type, $format, $filters, $rows, $generatedAt, $contents, $disk, $path): ReportGeneration {
                $file = File::query()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => sprintf('%s %s.%s', $type->name, $generatedAt->format('Y-m-d H.i'), $format->extension()),
                    'mime_type' => $format->mimeType(),
                    'size_bytes' => strlen($contents),
                    'sha256' => hash('sha256', $contents),
                    'uploaded_by' => $user->id,
                ]);

                $generation = new ReportGeneration;
                $generation->forceFill([
                    'report_type_id' => $type->id,
                    'format' => $format,
                    'filters' => $filters,
                    'row_count' => $rows->count(),
                    'file_id' => $file->id,
                    'generated_by' => $user->id,
                    'generated_at' => $generatedAt,
                ])->save();

                Audit::record(AuditAction::Generated, $generation, new: [
                    'report' => $type->code,
                    'format' => $format->value,
                    'filters' => $filters,
                    'row_count' => $rows->count(),
                ], actor: $user);

                return $generation;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    private function renderer(ReportFormat $format): ReportRenderer
    {
        return match ($format) {
            ReportFormat::Pdf => app(PdfRenderer::class),
            ReportFormat::Xlsx => app(XlsxRenderer::class),
        };
    }
}
