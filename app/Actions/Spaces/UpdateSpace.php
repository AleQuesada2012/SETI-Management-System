<?php

namespace App\Actions\Spaces;

use App\Models\Space;
use App\Models\User;
use App\Support\Database\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * RF_33 · Edit a space's attributes, RF_36 · update its capacity. Space-scoped (★): an Administrador edits only the
 * spaces they manage. The operational status is not changed here: RF_44 changes it, warning first about confirmed
 * future reservations. The `updated` audit entry is written by Auditable; duplicate codes and names are left to
 * the database, as in CreateSpace.
 */
final class UpdateSpace
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Space $space, array $input): Space
    {
        Gate::forUser($actor)->authorize('update', $space);

        $data = Validator::make($input, CreateSpace::rules(), attributes: __('spaces.fields'))->validate();

        try {
            return DB::transaction(function () use ($space, $data): Space {
                $space->update($data);

                return $space;
            });
        } catch (QueryException $e) {
            throw ConstraintViolation::toValidationException($e, field: CreateSpace::fieldOf($e));
        }
    }
}
