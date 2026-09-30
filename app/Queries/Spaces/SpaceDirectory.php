<?php

namespace App\Queries\Spaces;

use App\Models\Building;
use App\Models\Reporting\SpaceRow;
use App\Models\SpaceStatus;
use App\Models\SpaceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * Spaces as every list and report sees them (reporting.spaces): the spaces list (RF_53, RF_54 · @JoseHerrera21),
 * the space search (RF_42 · @Dizabu) and the spaces report (RF_64 · @AleQuesada2012). It owns the spaces filter
 * contract (.ai/rules/reports.md § Spaces schema): validate, normalize, offer choices, then query.
 *
 *     $filters = SpaceDirectory::validate(['space_type' => ['values' => [1, 2]], 'building' => ['values' => [4]]]);
 *     SpaceDirectory::query($filters)->get();   // (type 1 OR type 2) AND building 4
 */
final class SpaceDirectory
{
    /**
     * The categorical criteria: filter key => [reporting.spaces column, catalog model]. None is nullable, so none
     * accepts `include_missing`.
     *
     * @var array<string, array{0: string, 1: class-string<Model>}>
     */
    public const CRITERIA = [
        'space_type' => ['space_type_id', SpaceType::class],
        'space_status' => ['space_status_id', SpaceStatus::class],
        'building' => ['building_id', Building::class],
    ];

    /**
     * Criteria combine with AND; the values inside one criterion combine with OR. Pass validated filters (validate());
     * anything else is normalized first, so blank criteria never restrict.
     *
     * @param  array<string, mixed>  $filters  the same keys in the page, the report and report_generations.filters
     * @return Builder<SpaceRow>
     */
    public static function query(array $filters = []): Builder
    {
        $filters = self::normalize($filters);
        $query = SpaceRow::query();

        // TODO(RF_42, @Dizabu): TextSearch::apply($query, 'spaces', $filters['search'] ?? null).

        foreach (self::CRITERIA as $key => [$column]) {
            $query->when(isset($filters[$key]), fn (Builder $q) => $q->whereIn($column, $filters[$key]['values']));
        }

        return $query->orderBy('name');
    }

    /**
     * Check the shape and the references of the filters, then normalize them. Unsupported keys, malformed criteria and
     * IDs that never existed are Spanish validation errors that block the list or the report. IDs of catalog entries
     * removed later stay valid (historical references).
     *
     * @param  array<string, mixed>  $filters
     * @return array{search?: string, space_type?: array{values: list<int>}, space_status?: array{values: list<int>}, building?: array{values: list<int>}}
     *
     * @throws ValidationException
     */
    public static function validate(array $filters): array
    {
        $rules = ['search' => ['nullable', 'string', 'max:200']];
        $messages = [
            'search.string' => __('spaces.filters.errors.search'),
            'search.max' => __('spaces.filters.errors.search'),
        ];

        foreach (self::CRITERIA as $key => [, $model]) {
            $label = __("spaces.filters.criteria.{$key}");
            $rules[$key] = ['nullable', 'array:values'];
            $rules["{$key}.values"] = ['nullable', 'array'];
            $rules["{$key}.values.*"] = ['integer', Rule::exists((new $model)->getTable(), 'id')];
            $messages["{$key}.array"] = __('spaces.filters.errors.shape', ['filter' => $label]);
            $messages["{$key}.values.array"] = __('spaces.filters.errors.shape', ['filter' => $label]);
            $messages["{$key}.values.*.integer"] = __('spaces.filters.errors.unknown_value', ['filter' => $label]);
            $messages["{$key}.values.*.exists"] = __('spaces.filters.errors.unknown_value', ['filter' => $label]);
        }

        $validator = Validator::make($filters, $rules, $messages)->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (array_diff(array_keys($filters), ['search', ...array_keys(self::CRITERIA)]) as $key) {
                $validator->errors()->add((string) $key, __('spaces.filters.errors.unknown_key', ['key' => $key]));
            }
        });

        $validator->validate();

        return self::normalize($filters);
    }

    /**
     * Drop empty restrictions and duplicate selections, so `{}` means "every space" and equal selections store the
     * same way. Values that are not positive integers are dropped (validate() reports them first).
     *
     * @param  array<string, mixed>  $filters
     * @return array{search?: string, space_type?: array{values: list<int>}, space_status?: array{values: list<int>}, building?: array{values: list<int>}}
     */
    public static function normalize(array $filters): array
    {
        $normalized = [];

        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';
        if ($search !== '') {
            $normalized['search'] = $search;
        }

        foreach (array_keys(self::CRITERIA) as $key) {
            $values = $filters[$key]['values'] ?? [];
            $ids = collect(is_array($values) ? $values : [])
                ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
                ->map(fn (int|string $value): int => (int) $value)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()->sort()->values()->all();

            if ($ids !== []) {
                $normalized[$key] = ['values' => $ids];
            }
        }

        return $normalized;
    }

    /**
     * The options of each criterion: the current catalog entries plus removed ones some listed space still uses,
     * labelled as removed. They come from the whole dataset, so selecting a criterion never shrinks the others.
     *
     * @return array<string, list<array{id: int, label: string, removed: bool}>>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::CRITERIA as $key => [$column, $model]) {
            $choices[$key] = $model::query()
                ->withTrashed()
                ->where(fn (Builder $q) => $q->whereNull('deleted_at')->orWhereIn('id', SpaceRow::query()->select($column)))
                ->when($model === SpaceStatus::class, fn (Builder $q) => $q->orderBy('sort_order'))
                ->orderBy('name')
                ->get()
                ->map(fn (Model $entry): array => [
                    'id' => (int) $entry->getKey(),
                    'label' => $entry->getAttribute('deleted_at') === null
                        ? (string) $entry->getAttribute('name')
                        : __('spaces.filters.removed', ['name' => $entry->getAttribute('name')]),
                    'removed' => $entry->getAttribute('deleted_at') !== null,
                ])
                ->all();
        }

        return $choices;
    }
}
