<?php

namespace App\Enums;

/**
 * How an audited action reached the system (audit_logs.channel).
 */
enum AuditChannel: string
{
    case Web = 'web';
    case Api = 'api';
    case Cli = 'cli';
    case System = 'system';

    /** Spanish label for the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'API',
            self::Cli => 'Consola',
            self::System => 'Sistema',
        };
    }
}
