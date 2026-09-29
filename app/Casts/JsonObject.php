<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use stdClass;

/**
 * A jsonb column that must hold an object (CHECK jsonb_typeof(col) = 'object'). Laravel's `array` cast writes an
 * empty PHP array as `[]`; this cast writes `{}`.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>>
 */
final class JsonObject implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) json_encode($value === null || $value === [] ? new stdClass : $value, JSON_UNESCAPED_UNICODE);
    }
}
