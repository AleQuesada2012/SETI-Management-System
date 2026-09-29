<?php

namespace App\Support\Audit;

use App\Enums\AuditChannel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Answers "who, how and from where" for the audit entry being written (ADR-0002).
 *
 * - request_id groups every entry written by one user action. The AssignRequestId middleware
 *   sets it per HTTP request; Laravel's Context carries it into queued jobs, so a job's entries
 *   share the id of the action that dispatched it.
 * - channel: HTTP → web (or api for /api/*), artisan → cli, queued jobs → system.
 */
final class AuditContext
{
    public const REQUEST_ID = 'request_id';

    public const CHANNEL_OVERRIDE = 'audit.channel';

    public function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function requestId(): string
    {
        $id = Context::get(self::REQUEST_ID);

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid7();
            Context::add(self::REQUEST_ID, $id);
        }

        return $id;
    }

    public function channel(): AuditChannel
    {
        $override = Context::getHidden(self::CHANNEL_OVERRIDE);

        if (is_string($override) && ($channel = AuditChannel::tryFrom($override)) !== null) {
            return $channel;
        }

        if (! $this->isHttp()) {
            return AuditChannel::Cli;
        }

        return request()->is('api/*') ? AuditChannel::Api : AuditChannel::Web;
    }

    public function ipAddress(): ?string
    {
        return $this->isHttp() ? request()->ip() : null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->isHttp() ? request()->userAgent() : null;

        return $agent === null ? null : Str::limit($agent, 1000, '');
    }

    /** True while handling an HTTP request (including Livewire updates), false in artisan and queue workers. */
    private function isHttp(): bool
    {
        return app()->bound('request') && request()->route() !== null;
    }
}
