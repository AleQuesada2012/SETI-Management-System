<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the current session and sends the user to sign in, telling them why. The reason travels in the URL, not in the
 * session: when this answers a Livewire request, the browser follows the redirect inside that request and only then
 * loads the sign-in page, so a flashed message would already be gone (EnsureUserIsActive, EnforceSessionLifetime).
 */
final class SignedOut
{
    /** Reason in the sign-in URL → the message the sign-in page shows. */
    public const array REASONS = [
        'expired' => 'auth.session_expired',
        'deactivated' => 'auth.deactivated',
    ];

    /**
     * @param  key-of<self::REASONS>  $reason
     */
    public static function because(Request $request, string $reason): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', ['reason' => $reason]);
    }

    /** The message for the reason in the sign-in URL, or null for none or one this class did not write. */
    public static function message(mixed $reason): ?string
    {
        return is_string($reason) && isset(self::REASONS[$reason]) ? __(self::REASONS[$reason]) : null;
    }
}
