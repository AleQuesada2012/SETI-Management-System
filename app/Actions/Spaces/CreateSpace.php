<?php

namespace App\Actions\Spaces;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\SpaceStatus;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * RF_32 · Register a new space. It starts in the first operational status (Disponible); changing it is RF_44.
 * An Administrador who creates a space becomes its first manager in the same transaction, otherwise they could not
 * edit it (Agent-DB.md §6). Duplicate codes and names are left to the database (spaces_code_unique,
 * spaces_name_unique) and come back as Spanish validation messages.
 */
final class CreateSpace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Space
    {
        Gate::forUser($actor)->authorize('create', Space::class);

        $data = Validator::make($input, self::rules(), attributes: __('spaces.fields'))->validate();

        try {
            return DB::transaction(function () use ($actor, $data): Space {
                $space = Space::query()->create([
                    ...$data,
                    'space_status_id' => SpaceStatus::query()->orderBy('sort_order')->value('id'),
                ]);

                if ($actor->hasRole(SystemRole::Administrador->value)) {
                    $space->managers()->attach($actor->id, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
                    $actor->refreshManagedSpaces();

                    Audit::record(AuditAction::ManagerAssigned, $space, new: ['manager' => $actor->auditLabel()], actor: $actor);
                }

                return $space;
            });
        } catch (QueryException $e) {
            $field = match (ConstraintViolation::ruleName($e)) {
                'spaces_code_unique', 'spaces_code_check' => 'code',
                'spaces_name_unique', 'spaces_name_check' => 'name',
                'spaces_capacity_check' => 'capacity',
                default => 'form',
            };

            throw ConstraintViolation::toValidationException($e, field: $field);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'space_type_id' => ['required', 'integer', Rule::exists('space_types', 'id')->whereNull('deleted_at')],
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')->whereNull('deleted_at')],
            'floor' => ['nullable', 'string', 'max:20'],
            'location_detail' => ['nullable', 'string', 'max:200'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
