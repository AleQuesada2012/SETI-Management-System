<?php

namespace App\Actions\Spaces;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\Space;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * RF_40 · Put an Administrador in charge of one or more spaces (space_managers, ADR-0009), any space type. Only
 * holders of the Administrador role can be managers (Agent-DB.md §6). Spaces the user already manages are skipped.
 * A pivot change fires no model event, so each new assignment writes its `manager_assigned` entry here, in the same
 * transaction, like CreateSpace. Implemented by @Rubiocai (planning round 3); spaces owner @Dizabu reviews.
 */
final class AssignSpaceManager
{
    /**
     * @param  array<int, mixed>  $spaceIds
     * @return Collection<int, Space> the spaces newly assigned, by code
     */
    public function handle(User $actor, User $manager, array $spaceIds): Collection
    {
        /** @var array{spaces: list<int>} $data */
        $data = Validator::make(['spaces' => $spaceIds], [
            'spaces' => ['required', 'array', 'min:1'],
            'spaces.*' => ['integer', 'distinct', Rule::exists('spaces', 'id')->whereNull('deleted_at')],
        ], attributes: [
            'spaces' => __('spaces.managers.spaces'),
            'spaces.*' => __('spaces.managers.spaces'),
        ])->validate();

        $spaces = Space::query()->whereKey($data['spaces'])->orderBy('code')->get();

        $spaces->each(fn (Space $space) => Gate::forUser($actor)->authorize('assignManagers', $space));

        if (! $manager->hasRole(SystemRole::Administrador->value)) {
            throw ValidationException::withMessages(['spaces' => __('spaces.managers.not_administrador', ['user' => $manager->name])]);
        }

        $assigned = DB::transaction(function () use ($actor, $manager, $spaces): Collection {
            $assignedAt = now();

            $attached = $manager->managedSpaces()->syncWithoutDetaching(
                $spaces->mapWithKeys(fn (Space $space): array => [
                    $space->id => ['assigned_by' => $actor->id, 'assigned_at' => $assignedAt],
                ])->all(),
            )['attached'];

            $assigned = $spaces->whereIn('id', $attached)->values();

            $assigned->each(fn (Space $space) => Audit::record(
                AuditAction::ManagerAssigned,
                $space,
                new: ['manager' => $manager->auditLabel()],
                actor: $actor,
            ));

            return $assigned;
        });

        $manager->refreshManagedSpaces();

        return $assigned;
    }
}
