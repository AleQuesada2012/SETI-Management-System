<?php

namespace App\Actions\Assets;

use App\Enums\AssetEventKind;
use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\ColorGroup;
use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetEvent;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\ConstraintViolation;
use App\Support\Files\FileRules;
use App\Support\Files\FileStorage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * RF_13 · Register that an asset is damaged: an append-only `damage` AssetEvent with its date and description, optional
 * evidence photos or PDFs (asset_event_evidence → files, on RF_41's upload mechanism), and the asset's condition set to
 * the red-group condition the user picks ("Dañado" by default), in one transaction (Agent-DB.md §8.2, §8.4). Choosing
 * the red condition here is what keeps an undamaged asset out of the form: there is no damage without one.
 *
 * Only an asset in service or in maintenance can be damaged: a lost asset is not at hand and a decommissioned one is
 * read-only. The lifecycle status does not change; a repair (RF_11) later points at this event. An Administrador
 * registers damage of assets in, or last in, a space they manage (★, AssetPolicy::registerEvent). The `created` audit
 * entry of the event and the `updated` one of the asset's condition come from the Auditable models, plus an `attached`
 * entry per evidence file. If the transaction fails, the stored bytes are removed.
 */
final class RegisterAssetDamage
{
    public const MAX_EVIDENCE = 10;

    /**
     * @param  array<string, mixed>  $input  occurred_on, description, condition_id, evidence (uploaded files, optional)
     */
    public function handle(User $actor, Asset $asset, array $input): AssetEvent
    {
        Gate::forUser($actor)->authorize('registerEvent', $asset);
        self::ensureAllowed($asset);

        /** @var array{occurred_on: string, description: string, condition_id: int|string, evidence?: list<UploadedFile>} $data */
        $data = Validator::make($input, self::rules($asset), [
            ...RegisterAssetEvent::messages(),
            'condition_id.exists' => __('assets.events.errors.condition'),
        ], __('assets.events.fields'))->validate();

        $storage = app(FileStorage::class);

        try {
            return DB::transaction(function () use ($actor, $asset, $data, $storage): AssetEvent {
                $current = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('registerEvent', $current);
                self::ensureAllowed($current);

                $event = $current->events()->create([
                    'kind' => AssetEventKind::Damage,
                    'occurred_on' => $data['occurred_on'],
                    'description' => $data['description'],
                    'registered_by' => $actor->id,
                ]);

                foreach ($data['evidence'] ?? [] as $upload) {
                    $file = $storage->store($upload, $actor, "assets/{$current->id}/events/{$event->id}");
                    $event->evidence()->attach($file->id);

                    Audit::record(AuditAction::Attached, $event, new: ['evidence' => $file->original_name], actor: $actor);
                }

                $current->update(['condition_id' => (int) $data['condition_id']]);

                return $event;
            });
        } catch (Throwable $e) {
            $storage->discardWritten();

            if ($e instanceof QueryException) {
                $field = match (ConstraintViolation::ruleName($e)) {
                    'asset_events_description_check' => 'description',
                    default => 'form',
                };

                throw ConstraintViolation::toValidationException($e, field: $field);
            }

            throw $e;
        }
    }

    /** A damage is registered on an asset at hand that may still change (Agent-DB.md §8.4). */
    public static function isAllowed(Asset $asset): bool
    {
        return in_array($asset->status, [AssetStatus::InService, AssetStatus::InMaintenance], true);
    }

    /**
     * The red-group conditions the damaged asset may take, "Dañado" first.
     *
     * @return Collection<int, AssetCondition>
     */
    public static function conditions(): Collection
    {
        return AssetCondition::query()->where('color_group', ColorGroup::Red)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Asset $asset): array
    {
        return [
            'occurred_on' => RegisterAssetEvent::occurredOnRules($asset),
            'description' => ['required', 'string', 'max:1000'],
            'condition_id' => [
                'required',
                'integer',
                Rule::exists('asset_conditions', 'id')->where('color_group', ColorGroup::Red->value)->whereNull('deleted_at'),
            ],
            'evidence' => ['nullable', 'array', 'max:'.self::MAX_EVIDENCE],
            'evidence.*' => FileRules::document(),
        ];
    }

    private static function ensureAllowed(Asset $asset): void
    {
        if (! self::isAllowed($asset)) {
            throw ValidationException::withMessages(['kind' => __('assets.events.errors.kind')]);
        }
    }
}
