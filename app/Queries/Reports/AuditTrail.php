<?php

namespace App\Queries\Reports;

use App\Enums\AuditAction;
use App\Models\Reporting\AuditLogRow;
use App\Models\Reporting\UserRow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/** RF_68: immutable audit snapshots filtered by actor, operation and Costa Rica calendar days. */
final class AuditTrail
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLogRow>
     */
    public static function query(array $filters = []): Builder
    {
        $query = AuditLogRow::query();
        if (isset($filters['actor'])) {
            $query->whereIn('actor_id', $filters['actor']['values']);
        }
        if (isset($filters['action'])) {
            $query->whereIn('action', $filters['action']['values']);
        }
        foreach (['from', 'to'] as $bound) {
            if (isset($filters['occurred_at'][$bound])) {
                $date = CarbonImmutable::parse($filters['occurred_at'][$bound], 'America/Costa_Rica')->startOfDay();
                $query->where('occurred_at', $bound === 'from' ? '>=' : '<',
                    ($bound === 'to' ? $date->addDay() : $date)->toIso8601String());
            }
        }

        return $query->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function validate(array $filters): array
    {
        Validator::make($filters, [
            'actor' => ['nullable', 'array:values'], 'actor.values' => ['nullable', 'array'],
            'actor.values.*' => ['required', 'integer', Rule::exists('users', 'id')],
            'action' => ['nullable', 'array:values'], 'action.values' => ['nullable', 'array'],
            'action.values.*' => ['required', 'string', Rule::enum(AuditAction::class)],
            'occurred_at' => ['nullable', 'array:from,to'],
            'occurred_at.from' => ['nullable', 'date_format:Y-m-d'],
            'occurred_at.to' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'actor.array' => __('reports.filters.errors.shape'), 'actor.values.array' => __('reports.filters.errors.shape'),
            'actor.values.*.integer' => __('reports.filters.errors.value'), 'actor.values.*.exists' => __('reports.filters.errors.value'),
            'actor.values.*.required' => __('reports.filters.errors.value'),
            'action.array' => __('reports.filters.errors.shape'), 'action.values.array' => __('reports.filters.errors.shape'),
            'action.values.*.enum' => __('reports.filters.errors.value'),
            'action.values.*.required' => __('reports.filters.errors.value'), 'action.values.*.string' => __('reports.filters.errors.value'),
            'occurred_at.array' => __('reports.filters.errors.shape'),
            'occurred_at.from.date_format' => __('reports.filters.errors.date'), 'occurred_at.to.date_format' => __('reports.filters.errors.date'),
        ])->after(function (ValidatorInstance $validator) use ($filters): void {
            foreach (['actor', 'action', 'occurred_at'] as $key) {
                if (isset($filters[$key]) && ! is_array($filters[$key])) {
                    $validator->errors()->add($key, __('reports.filters.errors.shape'));
                }
                if (in_array($key, ['actor', 'action'], true) && is_array($filters[$key] ?? null)
                    && isset($filters[$key]['values']) && ! is_array($filters[$key]['values'])) {
                    $validator->errors()->add($key.'.values', __('reports.filters.errors.shape'));
                }
            }
            foreach (array_diff(array_keys($filters), ['actor', 'action', 'occurred_at']) as $key) {
                $validator->errors()->add((string) $key, __('reports.filters.errors.unknown', ['key' => $key]));
            }
            $dates = is_array($filters['occurred_at'] ?? null) ? $filters['occurred_at'] : [];
            if (! $validator->errors()->any() && ! empty($dates['from']) && ! empty($dates['to']) && $dates['from'] > $dates['to']) {
                $validator->errors()->add('occurred_at.to', __('reports.filters.errors.range'));
            }
        })->validate();

        $normalized = [];
        foreach (['actor', 'action'] as $key) {
            $values = array_values(array_unique($filters[$key]['values'] ?? []));
            if ($key === 'actor') {
                $values = array_values(array_unique(array_map(intval(...), $values)));
            }
            sort($values);
            if ($values !== []) {
                $normalized[$key] = ['values' => $values];
            }
        }
        $dates = array_filter($filters['occurred_at'] ?? [], fn (mixed $value): bool => $value !== null && trim($value) !== '');
        if ($dates !== []) {
            $normalized['occurred_at'] = $dates;
        }

        return $normalized;
    }

    /** @return array<string, list<array{id: int|string, label: string}>> */
    public static function choices(): array
    {
        return [
            'actor' => UserRow::query()->orderBy('name')->orderBy('id')->get()
                ->map(fn (UserRow $user): array => ['id' => $user->id, 'label' => $user->account_status === 'deactivated'
                    ? __('reports.filters.deactivated', ['name' => $user->name]) : $user->name])->all(),
            'action' => array_map(fn (AuditAction $action): array => ['id' => $action->value, 'label' => $action->label()], AuditAction::cases()),
        ];
    }
}
