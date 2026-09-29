<?php

namespace App\Support\Database;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Turns an integrity error raised by PostgreSQL into a Spanish validation message (Agent-DB.md §14.11).
 *
 * Every constraint and trigger rule names itself in the error message ("... violates constraint "x""
 * or "... violates rule "x""), so one regex finds the rule and lang/es/db.php holds the text:
 *
 *     try {
 *         $action->handle(...);
 *     } catch (QueryException $e) {
 *         throw ConstraintViolation::toValidationException($e, field: 'starts_at');
 *     }
 *
 * Validate first for friendly messages, but let the database be the final judge: under
 * concurrency a pre-check is never enough (e.g. reservations_no_overlap).
 */
final class ConstraintViolation
{
    /** SQLSTATEs that mean "the data broke a rule", not "the code has a bug". */
    private const INTEGRITY_STATES = ['23505', '23514', '23503', '23P01'];

    public static function ruleName(QueryException $exception): ?string
    {
        return preg_match('/(?:constraint|rule) "([a-z0-9_]+)"/', $exception->getMessage(), $matches) === 1
            ? $matches[1]
            : null;
    }

    public static function isIntegrityViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), self::INTEGRITY_STATES, true);
    }

    /** The Spanish message for the violated rule, or a generic one. */
    public static function message(QueryException $exception): string
    {
        $rule = self::ruleName($exception);
        $key = 'db.'.($rule ?? 'unknown');

        return trans()->has($key) ? __($key) : __('db.unknown');
    }

    /**
     * Rethrows errors that are not integrity violations (e.g. append-only history, a code bug).
     */
    public static function toValidationException(QueryException $exception, string $field = 'form'): ValidationException
    {
        if (! self::isIntegrityViolation($exception)) {
            throw $exception;
        }

        return ValidationException::withMessages([$field => self::message($exception)]);
    }
}
