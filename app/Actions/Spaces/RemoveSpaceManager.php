<?php

namespace App\Actions\Spaces;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\Space;
use App\Models\User;
use App\Support\Audit\Audit;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * RF_43 · Take one or more spaces away from a space manager (space_managers, ADR-0009). It refuses when the user
 * manages no space, or when a chosen space is not one of theirs. A pivot change fires no model event, so each removal
 * writes its `manager_removed` entry here, in the same transaction. It does not check the Administrador role, so
 * RF_86/RF_87 can reuse it after taking that role away. Implemented by @Rubiocai; spaces owner @Dizabu reviews.
 */
final class RemoveSpaceManager
{
    /**
     * @param  array<int, mixed>  $spaceIds
     * @return Collection<int, Space> the spaces removed, by code
     */
    public function handle(User $actor, User $manager, array $spaceIds): Collection
    {
        Gate::forUser($actor)->authorize(PermissionName::SpacesAssignManagers->value);

        $managedIds = $manager->managedSpaces()->pluck('spaces.id')->map(fn ($id): int => (int) $id)->all();

        if ($managedIds === []) {
            throw ValidationException::withMessages(['spaces' => __('spaces.managers.none_to_remove', ['user' => $manager->name])]);
        }

        /** @var array{spaces: list<int>} $data */
        $data = Validator::make(['spaces' => $spaceIds], [
            'spaces' => ['required', 'array', 'min:1'],
            'spaces.*' => ['integer', 'distinct', fn (string $attribute, mixed $value, Closure $fail) => in_array((int) $value, $managedIds, true)
                ? null
                : $fail(__('spaces.managers.not_managed', ['user' => $manager->name]))],
        ], attributes: [
            'spaces' => __('spaces.managers.spaces'),
            'spaces.*' => __('spaces.managers.spaces'),
        ])->validate();

        $spaces = Space::query()->whereKey($data['spaces'])->orderBy('code')->get();

        $spaces->each(fn (Space $space) => Gate::forUser($actor)->authorize('assignManagers', $space));

        DB::transaction(function () use ($actor, $manager, $spaces): void {
            $manager->managedSpaces()->detach($spaces->modelKeys());

            $spaces->each(fn (Space $space) => Audit::record(
                AuditAction::ManagerRemoved,
                $space,
                old: ['manager' => $manager->auditLabel()],
                actor: $actor,
            ));
        });

        $manager->refreshManagedSpaces();

        return $spaces;
    }
}
